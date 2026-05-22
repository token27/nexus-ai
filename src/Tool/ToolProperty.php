<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

/**
 * Defines a single parameter for a tool.
 *
 * Each ToolProperty maps to a JSON Schema property fragment via toSchema().
 * Used inside Tool::$properties to describe the tool's input parameters.
 *
 * Unlike Neuron AI's mutable ToolProperty, this is a readonly value object
 * with public properties for direct access in Tool::getParameters().
 *
 * @see \Token27\NexusAI\Tool\Tool
 * @see \Token27\NexusAI\Tool\PropertyType
 */
final readonly class ToolProperty
{
    /**
     * @param string $name Parameter name (e.g., 'city').
     * @param PropertyType $type JSON Schema type for this parameter.
     * @param string|null $description Description for the LLM explaining this parameter.
     * @param bool $required Whether this parameter is required.
     * @param array<string>|null $enum Allowed values (e.g., ['celsius', 'fahrenheit']).
     * @param mixed $default Default value for the parameter.
     */
    public function __construct(
        public string $name,
        public PropertyType $type,
        public ?string $description = null,
        public bool $required = false,
        public ?array $enum = null,
        public mixed $default = null,
    ) {
    }

    /**
     * Generates the JSON Schema fragment for this property.
     *
     * Output example: {'type': 'string', 'description': 'City name', 'enum': ['a', 'b']}
     *
     * @return array<string, mixed> JSON Schema fragment.
     */
    public function toSchema(): array
    {
        $schema = ['type' => $this->type->value];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }
}
