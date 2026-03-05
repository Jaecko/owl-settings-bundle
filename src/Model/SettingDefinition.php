<?php

namespace OwlConcept\SettingsBundle\Model;

class SettingDefinition
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly mixed $defaultValue = null,
        private readonly array $options = [],
        private readonly ?string $helpText = null,
        private readonly array $constraints = [],
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

    public function getType(): string
    {
        return $this->type;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Get a specific option value or a default.
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Whether this field is a select with defined options.
     */
    public function hasSelectOptions(): bool
    {
        return $this->type === 'select' && !empty($this->options['options']);
    }

    /**
     * Get select choices as ['value' => 'label'].
     */
    public function getSelectOptions(): array
    {
        return $this->options['options'] ?? [];
    }
}
