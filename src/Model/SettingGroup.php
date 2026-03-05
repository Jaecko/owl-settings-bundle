<?php

namespace OwlConcept\SettingsBundle\Model;

class SettingGroup
{
    /**
     * @param string              $key         Group identifier (e.g., 'general', 'email')
     * @param string              $label       Display label (e.g., 'Paramètres généraux')
     * @param SettingDefinition[] $settings    Ordered list of settings in this group
     * @param ?string             $icon        Optional icon identifier
     * @param ?string             $role        Optional required role to access this group
     * @param ?string             $description Optional group description
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly array $settings = [],
        private readonly ?string $icon = null,
        private readonly ?string $role = null,
        private readonly ?string $description = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return SettingDefinition[] */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get a setting definition by its key within this group.
     */
    public function getSetting(string $key): ?SettingDefinition
    {
        foreach ($this->settings as $setting) {
            if ($setting->getKey() === $key) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * Get the fully qualified key for a setting: 'group.setting_key'.
     */
    public function getQualifiedKey(string $settingKey): string
    {
        return $this->key . '.' . $settingKey;
    }
}
