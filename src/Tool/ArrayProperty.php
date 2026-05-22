<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

/**
 * Defines an array-typed parameter for a tool.
 *
 * Generates JSON Schema with {type: 'array', items: {...}} format.
 * The $items property defines the schema for each element in the array.
 *
 * Example output: {'type': 'array', 'description': 'List of tags', 'items': {'type': 'string'}}
 *
 * @see \Token27\NexusAI\Tool\ToolProperty
 * @see \Token27\NexusAI\Tool\ObjectProperty
 */
final readonly class ArrayProperty
{
    /**
     * @param string $name Parameter name.
     * @param string|null $description Description for the LLM.
     * @param bool $required Whether this parameter is required.
     * @param ToolProperty|ArrayProperty|ObjectProperty $items Schema definition for each array element.
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $required = false,
        public ToolProperty|ArrayProperty|ObjectProperty $items = new ToolProperty('item', PropertyType::String),
    ) {
    }

    /**
     * Generates the JSON Schema fragment for this array property.
     *
     * @return array<string, mixed> JSON Schema fragment with type 'array' and items definition.
     */
    public function toSchema(): array
    {
        $schema = [
            'type' => 'array',
            'items' => $this->items->toSchema(),
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }
}
