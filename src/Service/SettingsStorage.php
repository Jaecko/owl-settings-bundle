<?php

namespace OwlConcept\SettingsBundle\Service;

use Doctrine\DBAL\Connection;

class SettingsStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $settingsTable = 'owl_settings',
        private readonly string $preferencesTable = 'owl_user_preferences',
    ) {
    }

    // -----------------------------------------------------------------------
    // Global Settings
    // -----------------------------------------------------------------------

    /**
     * Get a single global setting value.
     * Returns null if not found in DB.
     */
    public function getSetting(string $key): ?string
    {
        $result = $this->connection->fetchOne(
            sprintf('SELECT setting_value FROM %s WHERE setting_key = ?', $this->settingsTable),
            [$key]
        );

        return $result === false ? null : $result;
    }

    /**
     * Get all global settings as ['key' => 'value'].
     *
     * @return array<string, ?string>
     */
    public function getAllSettings(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT setting_key, setting_value FROM %s', $this->settingsTable)
        );

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Save a global setting (upsert).
     */
    public function saveSetting(string $key, ?string $value, string $group, string $type): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $existing = $this->connection->fetchOne(
            sprintf('SELECT setting_key FROM %s WHERE setting_key = ?', $this->settingsTable),
            [$key]
        );

        if ($existing !== false) {
            $this->connection->update(
                $this->settingsTable,
                [
                    'setting_value' => $value,
                    'setting_group' => $group,
                    'setting_type' => $type,
                    'updated_at' => $now,
                ],
                ['setting_key' => $key]
            );
        } else {
            $this->connection->insert(
                $this->settingsTable,
                [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_group' => $group,
                    'setting_type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Save multiple global settings at once (within a transaction).
     *
     * @param array<string, array{value: ?string, group: string, type: string}> $settings
     */
    public function saveSettings(array $settings): void
    {
        $this->connection->transactional(function () use ($settings) {
            foreach ($settings as $key => $data) {
                $this->saveSetting($key, $data['value'], $data['group'], $data['type']);
            }
        });
    }

    // -----------------------------------------------------------------------
    // User Preferences
    // -----------------------------------------------------------------------

    /**
     * Get a single user preference value.
     * Returns null if not found in DB.
     */
    public function getUserPreference(string $userId, string $key): ?string
    {
        $result = $this->connection->fetchOne(
            sprintf('SELECT pref_value FROM %s WHERE user_id = ? AND pref_key = ?', $this->preferencesTable),
            [$userId, $key]
        );

        return $result === false ? null : $result;
    }

    /**
     * Get all preferences for a user as ['key' => 'value'].
     *
     * @return array<string, ?string>
     */
    public function getAllUserPreferences(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT pref_key, pref_value FROM %s WHERE user_id = ?', $this->preferencesTable),
            [$userId]
        );

        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['pref_key']] = $row['pref_value'];
        }

        return $prefs;
    }

    /**
     * Save a user preference (upsert).
     */
    public function saveUserPreference(string $userId, string $key, ?string $value): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $existing = $this->connection->fetchOne(
            sprintf('SELECT pref_key FROM %s WHERE user_id = ? AND pref_key = ?', $this->preferencesTable),
            [$userId, $key]
        );

        if ($existing !== false) {
            $this->connection->update(
                $this->preferencesTable,
                [
                    'pref_value' => $value,
                    'updated_at' => $now,
                ],
                ['user_id' => $userId, 'pref_key' => $key]
            );
        } else {
            $this->connection->insert(
                $this->preferencesTable,
                [
                    'user_id' => $userId,
                    'pref_key' => $key,
                    'pref_value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Save multiple user preferences at once (within a transaction).
     *
     * @param array<string, ?string> $preferences Key => value
     */
    public function saveUserPreferences(string $userId, array $preferences): void
    {
        $this->connection->transactional(function () use ($userId, $preferences) {
            foreach ($preferences as $key => $value) {
                $this->saveUserPreference($userId, $key, $value);
            }
        });
    }

    /**
     * Delete a specific user preference (reset to inherit/default).
     */
    public function deleteUserPreference(string $userId, string $key): void
    {
        $this->connection->delete(
            $this->preferencesTable,
            ['user_id' => $userId, 'pref_key' => $key]
        );
    }

    public function getSettingsTable(): string
    {
        return $this->settingsTable;
    }

    public function getPreferencesTable(): string
    {
        return $this->preferencesTable;
    }
}
