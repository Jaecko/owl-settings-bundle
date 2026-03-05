<?php

namespace OwlConcept\SettingsBundle\Builder;

use OwlConcept\SettingsBundle\Model\SettingGroup;
use OwlConcept\SettingsBundle\Model\UserPreferenceDefinition;
use OwlConcept\SettingsBundle\Service\SettingsService;

class SettingsBuilder
{
    /** @var SettingGroup[] */
    private array $groups = [];

    /** @var UserPreferenceDefinition[] */
    private array $userPreferences = [];

    private string $cssClassPrefix;

    public function __construct(
        private readonly SettingsService $settingsService,
        string $cssClassPrefix = 'owl-settings',
    ) {
        $this->cssClassPrefix = $cssClassPrefix;
    }

    /**
     * Start building a new settings group (returns a GroupBuilder).
     *
     * Usage:
     *   $builder->createGroup('general', 'Paramètres généraux')
     *       ->add('app_name', 'Nom', 'text', 'Mon CRM')
     *       ->build();
     */
    public function createGroup(string $key, string $label): GroupBuilder
    {
        return new GroupBuilder($this, $key, $label);
    }

    /**
     * Start building user preferences (returns a UserPreferencesBuilder).
     *
     * Usage:
     *   $builder->createUserPreferences()
     *       ->add('theme', 'Thème', 'select', 'light', ['options' => [...]])
     *       ->build();
     */
    public function createUserPreferences(): UserPreferencesBuilder
    {
        return new UserPreferencesBuilder($this);
    }

    /**
     * Called by GroupBuilder::build() to register a completed group.
     */
    public function registerGroup(SettingGroup $group): void
    {
        $this->groups[$group->getKey()] = $group;
        $this->settingsService->registerGroup($group);
    }

    /**
     * Called by UserPreferencesBuilder::build() to register all preferences.
     *
     * @param UserPreferenceDefinition[] $preferences
     */
    public function registerUserPreferences(array $preferences): void
    {
        $this->userPreferences = $preferences;
        $this->settingsService->registerUserPreferences($preferences);
    }

    /** @return SettingGroup[] */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /** @return UserPreferenceDefinition[] */
    public function getUserPreferences(): array
    {
        return $this->userPreferences;
    }

    public function getCssClassPrefix(): string
    {
        return $this->cssClassPrefix;
    }

    /**
     * Access the settings service for reading/writing values.
     */
    public function getSettingsService(): SettingsService
    {
        return $this->settingsService;
    }
}
