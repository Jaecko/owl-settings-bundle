<?php

namespace OwlConcept\SettingsBundle\Service;

use OwlConcept\SettingsBundle\Model\SettingDefinition;
use OwlConcept\SettingsBundle\Model\SettingGroup;
use OwlConcept\SettingsBundle\Model\UserPreferenceDefinition;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SettingsService
{
    private const CACHE_KEY_ALL_SETTINGS = 'owl_settings.all';
    private const CACHE_KEY_USER_PREFIX = 'owl_settings.user.';

    /** @var SettingGroup[] Registered groups (populated by the builder) */
    private array $groups = [];

    /** @var UserPreferenceDefinition[] Registered user preferences (populated by the builder) */
    private array $userPreferences = [];

    public function __construct(
        private readonly SettingsStorage $storage,
        private readonly CacheInterface $cache,
        private readonly Security $security,
        private readonly int $cacheTtl = 3600,
        private readonly string $userIdentifierMethod = 'getUserIdentifier',
    ) {
    }

    // -----------------------------------------------------------------------
    // Registration (called by the builder during app initialization)
    // -----------------------------------------------------------------------

    public function registerGroup(SettingGroup $group): void
    {
        $this->groups[$group->getKey()] = $group;
    }

    /** @param UserPreferenceDefinition[] $preferences */
    public function registerUserPreferences(array $preferences): void
    {
        foreach ($preferences as $pref) {
            $this->userPreferences[$pref->getKey()] = $pref;
        }
    }

    /** @return SettingGroup[] */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /** @return UserPreferenceDefinition[] */
    public function getUserPreferenceDefinitions(): array
    {
        return $this->userPreferences;
    }

    // -----------------------------------------------------------------------
    // Global Settings — Read
    // -----------------------------------------------------------------------

    /**
     * Get a global setting value by its fully qualified key (e.g., 'general.app_name').
     * Falls back to the default value from the definition if not stored in DB.
     */
    public function get(string $qualifiedKey): mixed
    {
        $allSettings = $this->getAllCached();

        if (array_key_exists($qualifiedKey, $allSettings)) {
            return $this->castValue($qualifiedKey, $allSettings[$qualifiedKey]);
        }

        // Fall back to default from definition
        $definition = $this->findSettingDefinition($qualifiedKey);

        return $definition?->getDefaultValue();
    }

    /**
     * Get all global settings as ['qualified_key' => value], merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $stored = $this->getAllCached();
        $result = [];

        foreach ($this->groups as $group) {
            foreach ($group->getSettings() as $setting) {
                $qualifiedKey = $group->getKey() . '.' . $setting->getKey();
                $result[$qualifiedKey] = array_key_exists($qualifiedKey, $stored)
                    ? $this->castValue($qualifiedKey, $stored[$qualifiedKey])
                    : $setting->getDefaultValue();
            }
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Global Settings — Write
    // -----------------------------------------------------------------------

    /**
     * Set a global setting value.
     */
    public function set(string $qualifiedKey, mixed $value): void
    {
        [$groupKey, $settingKey] = $this->parseQualifiedKey($qualifiedKey);
        $definition = $this->findSettingDefinition($qualifiedKey);
        $type = $definition?->getType() ?? 'text';

        $serialized = $this->serializeValue($value, $type);
        $this->storage->saveSetting($qualifiedKey, $serialized, $groupKey, $type);
        $this->invalidateSettingsCache();
    }

    /**
     * Save all settings for a group at once (used by form submission).
     *
     * @param string $groupKey The group key (e.g., 'general')
     * @param array  $values   ['setting_key' => value] (non-qualified keys)
     */
    public function saveGroup(string $groupKey, array $values): void
    {
        $group = $this->groups[$groupKey] ?? null;
        if ($group === null) {
            throw new \InvalidArgumentException(sprintf('Unknown settings group "%s".', $groupKey));
        }

        $toSave = [];
        foreach ($group->getSettings() as $setting) {
            if (array_key_exists($setting->getKey(), $values)) {
                $qualifiedKey = $groupKey . '.' . $setting->getKey();
                $toSave[$qualifiedKey] = [
                    'value' => $this->serializeValue($values[$setting->getKey()], $setting->getType()),
                    'group' => $groupKey,
                    'type' => $setting->getType(),
                ];
            }
        }

        $this->storage->saveSettings($toSave);
        $this->invalidateSettingsCache();
    }

    // -----------------------------------------------------------------------
    // User Preferences — Read
    // -----------------------------------------------------------------------

    /**
     * Get a user preference for the current user (or a specific user).
     *
     * Resolution order:
     *   1. Stored user preference in DB
     *   2. If inherit_from is set: global setting value
     *   3. Default value from the preference definition
     *
     * @param string      $key  Preference key (e.g., 'theme')
     * @param object|null $user Specific user object, or null for the current user
     */
    public function getUserPreference(string $key, ?object $user = null): mixed
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            // No user authenticated — return the fallback
            return $this->getUserPreferenceFallback($key);
        }

        $allPrefs = $this->getUserPreferencesCached($userId);

        if (array_key_exists($key, $allPrefs) && $allPrefs[$key] !== null) {
            return $this->castUserPrefValue($key, $allPrefs[$key]);
        }

        return $this->getUserPreferenceFallback($key);
    }

    /**
     * Get all user preferences for the current user, merged with defaults and inherited values.
     *
     * @return array<string, mixed>
     */
    public function getAllUserPreferences(?object $user = null): array
    {
        $userId = $this->resolveUserId($user);
        $stored = $userId !== null ? $this->getUserPreferencesCached($userId) : [];
        $result = [];

        foreach ($this->userPreferences as $pref) {
            if (array_key_exists($pref->getKey(), $stored) && $stored[$pref->getKey()] !== null) {
                $result[$pref->getKey()] = $this->castUserPrefValue($pref->getKey(), $stored[$pref->getKey()]);
            } else {
                $result[$pref->getKey()] = $this->getUserPreferenceFallback($pref->getKey());
            }
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // User Preferences — Write
    // -----------------------------------------------------------------------

    /**
     * Save a user preference for the current user (or a specific user).
     */
    public function setUserPreference(string $key, mixed $value, ?object $user = null): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            throw new \LogicException('Cannot save user preference: no authenticated user.');
        }

        $definition = $this->userPreferences[$key] ?? null;
        $type = $definition?->getType() ?? 'text';
        $serialized = $this->serializeValue($value, $type);

        $this->storage->saveUserPreference($userId, $key, $serialized);
        $this->invalidateUserPreferencesCache($userId);
    }

    /**
     * Save all user preferences at once (used by form submission).
     *
     * @param array<string, mixed> $values ['pref_key' => value]
     */
    public function saveAllUserPreferences(array $values, ?object $user = null): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            throw new \LogicException('Cannot save user preferences: no authenticated user.');
        }

        $serialized = [];
        foreach ($values as $key => $value) {
            $definition = $this->userPreferences[$key] ?? null;
            $type = $definition?->getType() ?? 'text';
            $serialized[$key] = $this->serializeValue($value, $type);
        }

        $this->storage->saveUserPreferences($userId, $serialized);
        $this->invalidateUserPreferencesCache($userId);
    }

    /**
     * Reset a user preference to inherit/default.
     */
    public function resetUserPreference(string $key, ?object $user = null): void
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            throw new \LogicException('Cannot reset user preference: no authenticated user.');
        }

        $this->storage->deleteUserPreference($userId, $key);
        $this->invalidateUserPreferencesCache($userId);
    }

    // -----------------------------------------------------------------------
    // Private: Cache
    // -----------------------------------------------------------------------

    /**
     * Get all global settings from cache (or load from DB).
     *
     * @return array<string, ?string>
     */
    private function getAllCached(): array
    {
        return $this->cache->get(self::CACHE_KEY_ALL_SETTINGS, function (ItemInterface $item) {
            if ($this->cacheTtl > 0) {
                $item->expiresAfter($this->cacheTtl);
            }

            return $this->storage->getAllSettings();
        });
    }

    private function invalidateSettingsCache(): void
    {
        $this->cache->delete(self::CACHE_KEY_ALL_SETTINGS);
    }

    /**
     * Get all preferences for a user from cache (or load from DB).
     *
     * @return array<string, ?string>
     */
    private function getUserPreferencesCached(string $userId): array
    {
        $cacheKey = self::CACHE_KEY_USER_PREFIX . md5($userId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            if ($this->cacheTtl > 0) {
                $item->expiresAfter($this->cacheTtl);
            }

            return $this->storage->getAllUserPreferences($userId);
        });
    }

    private function invalidateUserPreferencesCache(string $userId): void
    {
        $cacheKey = self::CACHE_KEY_USER_PREFIX . md5($userId);
        $this->cache->delete($cacheKey);
    }

    // -----------------------------------------------------------------------
    // Private: User resolution
    // -----------------------------------------------------------------------

    private function resolveUserId(?object $user = null): ?string
    {
        if ($user === null) {
            $user = $this->security->getUser();
        }

        if ($user === null) {
            return null;
        }

        $method = $this->userIdentifierMethod;
        if (!method_exists($user, $method)) {
            throw new \LogicException(sprintf(
                'User object (%s) does not have method "%s". Configure owl_settings.user_identifier_method.',
                get_class($user),
                $method
            ));
        }

        return (string) $user->$method();
    }

    // -----------------------------------------------------------------------
    // Private: Fallback resolution for user preferences
    // -----------------------------------------------------------------------

    private function getUserPreferenceFallback(string $key): mixed
    {
        $definition = $this->userPreferences[$key] ?? null;
        if ($definition === null) {
            return null;
        }

        // If inherit_from is set, resolve the global setting
        if ($definition->inheritsFromGlobal()) {
            $globalValue = $this->get($definition->getInheritFrom());
            if ($globalValue !== null) {
                return $globalValue;
            }
        }

        return $definition->getDefaultValue();
    }

    // -----------------------------------------------------------------------
    // Private: Type casting & serialization
    // -----------------------------------------------------------------------

    /**
     * Find a SettingDefinition by its fully qualified key.
     */
    private function findSettingDefinition(string $qualifiedKey): ?SettingDefinition
    {
        [$groupKey, $settingKey] = $this->parseQualifiedKey($qualifiedKey);
        $group = $this->groups[$groupKey] ?? null;

        return $group?->getSetting($settingKey);
    }

    /**
     * Parse 'general.app_name' into ['general', 'app_name'].
     *
     * @return array{0: string, 1: string}
     */
    private function parseQualifiedKey(string $qualifiedKey): array
    {
        $parts = explode('.', $qualifiedKey, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid qualified setting key "%s". Expected format "group.key".',
                $qualifiedKey
            ));
        }

        return $parts;
    }

    /**
     * Cast a stored string value to its proper PHP type based on the definition.
     */
    private function castValue(string $qualifiedKey, ?string $storedValue): mixed
    {
        if ($storedValue === null) {
            return null;
        }

        $definition = $this->findSettingDefinition($qualifiedKey);
        if ($definition === null) {
            return $storedValue;
        }

        return $this->castByType($storedValue, $definition->getType());
    }

    private function castUserPrefValue(string $key, ?string $storedValue): mixed
    {
        if ($storedValue === null) {
            return null;
        }

        $definition = $this->userPreferences[$key] ?? null;
        if ($definition === null) {
            return $storedValue;
        }

        return $this->castByType($storedValue, $definition->getType());
    }

    private function castByType(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'number' => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
            default => $value,
        };
    }

    /**
     * Serialize a PHP value to a string for DB storage.
     */
    private function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
