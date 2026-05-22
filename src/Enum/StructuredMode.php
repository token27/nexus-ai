<?php

declare(strict_types=1);

namespace Token27\NexusAI\Enum;

/**
 * Strategy used by the driver to enforce structured (JSON) output.
 *
 * Different providers support different mechanisms for structured output.
 * This enum allows the user to choose explicitly, or let the driver decide
 * the best strategy via Auto mode.
 *
 * @see \Token27\NexusAI\Contract\SchemaInterface
 */
enum StructuredMode: string
{
    /**
     * The driver chooses the best strategy based on provider capabilities.
     * This is the default mode.
     */
    case Auto = 'auto';

    /**
     * Forces `response_format: {type: "json_object"}` plus a system prompt
     * instruction containing the schema. Compatible with all providers.
     */
    case Json = 'json';

    /**
     * Uses the tool calling mechanism to enforce the schema: defines a fake
     * "tool" with the schema as parameters and `tool_choice: required`.
     */
    case Tool = 'tool';

    /**
     * Uses native `response_format: {type: "json_schema", json_schema: {...}}`.
     * Only supported by OpenAI and some compatible providers.
     */
    case Native = 'native';
}
