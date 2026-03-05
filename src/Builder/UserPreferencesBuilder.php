<?php

namespace OwlConcept\SettingsBundle\Builder;

use OwlConcept\SettingsBundle\Model\UserPreferenceDefinition;

class UserPreferencesBuilder
{
    /** @var UserPreferenceDefinition[] */
    private array $preferences = [];

    public function __construct(
        private readonly SettingsBuilder $parent,
    ) {
    }

    /**
     * Add a user preference definition.
     *
     * @param string $key          Preference key
     * @param string $label        Display label
     * @param string $type         Field type: text, email, number, boolean, select, textarea, file, color, password
     * @param mixed  $defaultValue Default value (null = inherit from global if inherit_from is set)
     * @param array  $options      Type-specific options + 'inherit_from' => 'group.key'
     */
    public function add(
        string $key,
        string $label,
        string $type,
        mixed $defaultValue = null,
        array $options = [],
    ): self {
        $inheritFrom = $options['inherit_from'] ?? null;
        $helpText = $options['help'] ?? null;
        $constraints = $options['constraints'] ?? [];

        foreach (['min', 'max', 'required', 'regex', 'maxlength'] as $constraintKey) {
            if (isset($options[$constraintKey])) {
                $constraints[$constraintKey] = $options[$constraintKey];
            }
        }

        $this->preferences[] = new UserPreferenceDefinition(
            key: $key,
            label: $label,
            type: $type,
            defaultValue: $defaultValue,
            options: $options,
            inheritFrom: $inheritFrom,
            helpText: $helpText,
            constraints: $constraints,
        );

        return $this;
    }

    /**
     * Build and register all user preferences, returning the parent builder.
     */
    public function build(): SettingsBuilder
    {
        $this->parent->registerUserPreferences($this->preferences);

        return $this->parent;
    }
}
