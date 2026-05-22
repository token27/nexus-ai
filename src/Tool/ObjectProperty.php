<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

/**
 * Defines an object-typed parameter for a tool with nested sub-properties.
 *
 * Generates JSON Schema with {type: 'object', properties: {...}, required: [...]} format.
 * Supports nesting: sub-properties can be ToolProperty, ArrayProperty, or ObjectProperty.
 *
 * @see \Token27\NexusAI\Tool\ToolProperty
 * @see \Token27\NexusAI\Tool\ArrayProperty
 */
final readonly class ObjectProperty
{
    /**
     * @param string $name Parameter name.
     * @param string|null $description Description for the LLM.
     * @param bool $required Whether this parameter is required.
     * @param array<ToolProperty|ArrayProperty|ObjectProperty> $properties Sub-properties of the object.
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $required = false,
        public array $properties = [],
    ) {
    }

    /**
     * Generates the JSON Schema fragment for this object property.
     *
     * @return array<string, mixed> JSON Schema fragment with type 'object', properties, and required.
     */
    public function toSchema(): array
    {
        $schema = [
            'type' => 'object',
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        $props = [];
        $required = [];

        foreach ($this->properties as $prop) {
            $props[$prop->name] = $prop->toSchema();

            if ($prop->required) {
                $required[] = $prop->name;
            }
        }

        if ($props !== []) {
            $schema['properties'] = $props;
        }

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
