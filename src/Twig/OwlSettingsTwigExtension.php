<?php

namespace OwlConcept\SettingsBundle\Twig;

use OwlConcept\SettingsBundle\Model\SettingGroup;
use OwlConcept\SettingsBundle\Model\UserPreferenceDefinition;
use OwlConcept\SettingsBundle\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OwlSettingsTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('owl_setting', [$this, 'getSetting']),
            new TwigFunction('owl_user_pref', [$this, 'getUserPreference']),
            new TwigFunction('owl_settings_groups', [$this, 'getGroups']),
            new TwigFunction('owl_settings_all', [$this, 'getAllSettings']),
            new TwigFunction('owl_user_pref_definitions', [$this, 'getUserPreferenceDefinitions']),
            new TwigFunction('owl_user_pref_all', [$this, 'getAllUserPreferences']),
        ];
    }

    /**
     * {{ owl_setting('general.app_name') }}
     */
    public function getSetting(string $qualifiedKey): mixed
    {
        return $this->settingsService->get($qualifiedKey);
    }

    /**
     * {{ owl_user_pref('theme') }}
     */
    public function getUserPreference(string $key): mixed
    {
        return $this->settingsService->getUserPreference($key);
    }

    /**
     * Used by admin.html.twig to render tab groups.
     *
     * @return SettingGroup[]
     */
    public function getGroups(): array
    {
        return $this->settingsService->getGroups();
    }

    /**
     * All current global settings values.
     *
     * @return array<string, mixed>
     */
    public function getAllSettings(): array
    {
        return $this->settingsService->getAll();
    }

    /**
     * @return UserPreferenceDefinition[]
     */
    public function getUserPreferenceDefinitions(): array
    {
        return $this->settingsService->getUserPreferenceDefinitions();
    }

    /**
     * All current user preferences (for the current user).
     *
     * @return array<string, mixed>
     */
    public function getAllUserPreferences(): array
    {
        return $this->settingsService->getAllUserPreferences();
    }
}
