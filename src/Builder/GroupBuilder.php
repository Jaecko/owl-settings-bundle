<?php

namespace OwlConcept\SettingsBundle\Builder;

use OwlConcept\SettingsBundle\Model\SettingDefinition;
use OwlConcept\SettingsBundle\Model\SettingGroup;

class GroupBuilder
{
    /** @var SettingDefinition[] */
    private array $settings = [];
    private ?string $icon = null;
    private ?string $role = null;
    private ?string $description = null;

    public function __construct(
        private readonly SettingsBuilder $parent,
        private readonly string $key,
        private readonly string $label,
    ) {
    }

    /**
     * Add a setting to this group.
     *
     * @param string $key          Setting key (unique within the group)
     * @param string $label        Display label
     * @param string $type         Field type: text, email, number, boolean, select, textarea, file, color, password
     * @param mixed  $defaultValue Default value
     * @param array  $options      Type-specific options (e.g., 'options' for select, 'min'/'max' for number, 'help' for help text)
     */
    public function add(
        string $key,
        string $label,
        string $type,
        mixed $defaultValue = null,
        array $options = [],
    ): self {
        $helpText = $options['help'] ?? null;
        $constraints = $options['constraints'] ?? [];

        // Extract validation-related keys into constraints if present at top level
        foreach (['min', 'max', 'required', 'regex', 'maxlength'] as $constraintKey) {
            if (isset($options[$constraintKey])) {
                $constraints[$constraintKey] = $options[$constraintKey];
            }
        }

        $this->settings[] = new SettingDefinition(
            key: $key,
            label: $label,
            type: $type,
            defaultValue: $defaultValue,
            options: $options,
            helpText: $helpText,
            constraints: $constraints,
        );

        return $this;
    }

    /**
     * Set an icon for this group tab.
     */
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the required role to access this group.
     */
    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Set a description for this group.
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Build and register the group, returning the parent builder for chaining.
     */
    public function build(): SettingsBuilder
    {
        $group = new SettingGroup(
            key: $this->key,
            label: $this->label,
            settings: $this->settings,
            icon: $this->icon,
            role: $this->role,
            description: $this->description,
        );

        $this->parent->registerGroup($group);

        return $this->parent;
    }
}
