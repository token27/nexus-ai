<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

/**
 * Enum of possible JSON Schema types for tool properties.
 *
 * Maps directly to JSON Schema type values. Used by ToolProperty,
 * ArrayProperty, and ObjectProperty to define parameter types.
 *
 * @see \Token27\NexusAI\Tool\ToolProperty
 */
enum PropertyType: string
{
    /** JSON Schema string type. */
    case String = 'string';

    /** JSON Schema number type (float/double). */
    case Number = 'number';

    /** JSON Schema integer type. */
    case Integer = 'integer';

    /** JSON Schema boolean type. */
    case Boolean = 'boolean';

    /** JSON Schema array type. Use ArrayProperty for full definition. */
    case Array = 'array';

    /** JSON Schema object type. Use ObjectProperty for full definition. */
    case Object = 'object';
}
