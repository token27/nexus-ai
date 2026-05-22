<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput;

use function array_merge;
use function array_pop;

use BackedEnum;

use function class_exists;

use DateTimeImmutable;
use DateTimeInterface;

use function enum_exists;
use function in_array;
use function is_subclass_of;

use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Generates a JSON Schema from a PHP DTO class using Reflection.
 *
 * Reads public properties, their type hints, and `#[SchemaProperty]` attributes
 * to produce a JSON Schema array suitable for sending to LLM providers.
 *
 * Supported types:
 * - Primitives: string, int, float, bool
 * - Nullable types: ?string → ["string", "null"]
 * - Arrays: array → {type: "array", items: {...}}
 * - BackedEnum: → {type: "string"|"integer", enum: [...cases]}
 * - Nested classes: recursive sub-schema generation
 * - DateTimeImmutable/DateTimeInterface: → {type: "string", format: "date-time"}
 *
 * @see \Token27\NexusAI\StructuredOutput\SchemaProperty
 * @see \Token27\NexusAI\Contract\SchemaInterface
 */
final class SchemaGenerator
{
    /**
     * Track classes being processed to prevent infinite recursion.
     *
     * @var array<string>
     */
    private static array $processedClasses = [];

    /**
     * Generate a complete JSON Schema from a DTO class.
     *
     * @param string $className Fully qualified class name of the DTO.
     * @return array<string, mixed> The JSON Schema as a PHP array.
     * @throws ReflectionException If reflection on the class fails.
     */
    public static function generate(string $className): array
    {
        self::$processedClasses = [];

        return [
            ...self::generateClassSchema($className),
            'additionalProperties' => false,
        ];
    }

    /**
     * Generate schema for a single class (recursive entry point).
     *
     * @param string $className Fully qualified class name.
     * @return array<string, mixed> The class schema.
     * @throws ReflectionException
     */
    private static function generateClassSchema(string $className): array
    {
        if (!class_exists($className)) {
            return ['type' => 'object'];
        }

        $reflection = new ReflectionClass($className);

        // Circular reference protection
        if (in_array($className, self::$processedClasses, true)) {
            return ['type' => 'object'];
        }

        self::$processedClasses[] = $className;

        // Handle enum types passed as class names
        if ($reflection->isEnum() && is_subclass_of($className, \UnitEnum::class)) {
            $result = self::processEnum(new ReflectionEnum($className));
            array_pop(self::$processedClasses);

            return $result;
        }

        $schema = [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];

        $requiredProperties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $schema['properties'][$propertyName] = self::processProperty($property);

            // Determine if property is required
            $attribute = self::getPropertyAttribute($property);

            if ($attribute !== null) {
                if ($attribute->required) {
                    $requiredProperties[] = $propertyName;
                }
            } else {
                // Default logic: required if not nullable and has no default
                $type = $property->getType();
                $isNullable = $type !== null ? $type->allowsNull() : true;

                if (!$isNullable && !$property->hasDefaultValue()) {
                    $requiredProperties[] = $propertyName;
                }
            }
        }

        if ($requiredProperties !== []) {
            $schema['required'] = $requiredProperties;
        }

        array_pop(self::$processedClasses);

        return $schema;
    }

    /**
     * Process a single property into its JSON Schema representation.
     *
     * @param ReflectionProperty $property The property to process.
     * @return array<string, mixed> The property schema.
     * @throws ReflectionException
     */
    private static function processProperty(ReflectionProperty $property): array
    {
        $schema = [];
        $attribute = self::getPropertyAttribute($property);

        // Apply SchemaProperty metadata
        if ($attribute !== null) {
            if ($attribute->description !== null) {
                $schema['description'] = $attribute->description;
            }

            if ($attribute->default !== null) {
                $schema['default'] = $attribute->default;
            }
        }

        // Handle default values from the property itself
        if ($property->hasDefaultValue() && !isset($schema['default'])) {
            $defaultValue = $property->getDefaultValue();
            if ($defaultValue !== null) {
                $schema['default'] = $defaultValue;
            }
        }

        /** @var ReflectionNamedType|null $type */
        $type = $property->getType();
        $typeName = $type?->getName();

        // Build the type-specific schema
        if ($typeName === 'array') {
            $schema['type'] = 'array';
            $schema['items'] = self::inferArrayItemsSchema($property, $attribute);

            // Apply array constraints from attribute
            if ($attribute !== null) {
                if ($attribute->minItems !== null) {
                    $schema['minItems'] = $attribute->minItems;
                }
                if ($attribute->maxItems !== null) {
                    $schema['maxItems'] = $attribute->maxItems;
                }
            }
        } elseif ($typeName !== null && enum_exists($typeName)) {
            $enumSchema = self::processEnum(new ReflectionEnum($typeName));
            $schema = array_merge($schema, $enumSchema);
        } elseif ($typeName !== null && self::isDateTimeType($typeName)) {
            $schema['type'] = 'string';
            $schema['format'] = 'date-time';
        } elseif ($typeName !== null && class_exists($typeName)) {
            $classSchema = self::generateClassSchema($typeName);
            $schema = array_merge($schema, $classSchema);
        } elseif ($typeName !== null) {
            $typeSchema = self::mapPhpTypeToJsonSchema($typeName);
            $schema = array_merge($schema, $typeSchema);

            // Apply numeric constraints
            if ($attribute !== null && in_array($typeName, ['int', 'integer', 'float', 'double'], true)) {
                if ($attribute->minimum !== null) {
                    $schema['minimum'] = $attribute->minimum;
                }
                if ($attribute->maximum !== null) {
                    $schema['maximum'] = $attribute->maximum;
                }
            }
        } else {
            // No type hint — default to string
            $schema['type'] = 'string';
        }

        // Apply attribute overrides that work on any type
        if ($attribute !== null) {
            if ($attribute->enum !== null) {
                $schema['enum'] = $attribute->enum;
            }
            if ($attribute->format !== null) {
                $schema['format'] = $attribute->format;
            }
            if ($attribute->pattern !== null) {
                $schema['pattern'] = $attribute->pattern;
            }
        }

        // Handle nullable types
        if ($type !== null && $type->allowsNull() && isset($schema['type']) && !isset($schema['anyOf'])) {
            if (\is_array($schema['type'])) {
                if (!in_array('null', $schema['type'], true)) {
                    $schema['type'][] = 'null';
                }
            } else {
                $schema['type'] = [$schema['type'], 'null'];
            }
        }

        return $schema;
    }

    /**
     * Process an enum type into its JSON Schema representation.
     *
     * @param \ReflectionEnum<\UnitEnum> $enum The enum reflection.
     * @return array<string, mixed> The enum schema.
     */
    private static function processEnum(ReflectionEnum $enum): array
    {
        $schema = [
            'type' => 'string',
            'enum' => [],
        ];

        foreach ($enum->getCases() as $case) {
            if ($enum->isBacked()) {
                /** @var ReflectionEnumBackedCase $case */
                $backingValue = $case->getBackingValue();
                $schema['enum'][] = $backingValue;

                // If any backing value is integer, switch type
                if (\is_int($backingValue)) {
                    $schema['type'] = 'integer';
                }
            } else {
                $schema['enum'][] = $case->getName();
            }
        }

        return $schema;
    }

    /**
     * Map a PHP type name to its JSON Schema equivalent.
     *
     * @param string $typeName PHP type name.
     * @return array<string, mixed> The JSON Schema type definition.
     */
    private static function mapPhpTypeToJsonSchema(string $typeName): array
    {
        return match ($typeName) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'mixed' => ['type' => 'string'],
            default => ['type' => 'string'],
        };
    }

    /**
     * Infer the items schema for an array property.
     *
     * Resolution order:
     * 1. SchemaProperty $type attribute (explicit FQCN)
     * 2. PHPDoc @var ClassName[] annotation
     * 3. Fallback: untyped string items
     *
     * @param ReflectionProperty $property The property to inspect.
     * @param SchemaProperty|null $attribute The property attribute, if any.
     * @return array<string, mixed> The items schema.
     */
    private static function inferArrayItemsSchema(
        ReflectionProperty $property,
        ?SchemaProperty $attribute,
    ): array {
        // 1. Try SchemaProperty $type attribute
        if ($attribute !== null && $attribute->type !== null) {
            $itemType = $attribute->type;
            if (class_exists($itemType)) {
                return self::generateClassSchema($itemType);
            }
            if (enum_exists($itemType)) {
                return self::processEnum(new ReflectionEnum($itemType));
            }

            return self::mapPhpTypeToJsonSchema($itemType);
        }

        // 2. Try PHPDoc @var ClassName[]
        $docComment = $property->getDocComment();
        if ($docComment !== false) {
            if (preg_match('/@var\s+([^\[\]\s]+)\[\]/', $docComment, $matches)) {
                $itemType = ltrim($matches[1], '\\');
                if (class_exists($itemType)) {
                    return self::generateClassSchema($itemType);
                }
                if (enum_exists($itemType)) {
                    return self::processEnum(new ReflectionEnum($itemType));
                }

                return self::mapPhpTypeToJsonSchema($itemType);
            }
        }

        // 3. Fallback: untyped
        return ['type' => 'string'];
    }

    /**
     * Check if a type name is a DateTime-related class.
     *
     * @param string $typeName The type name to check.
     * @return bool True if the type is DateTime-related.
     */
    private static function isDateTimeType(string $typeName): bool
    {
        return $typeName === DateTimeImmutable::class
            || $typeName === \DateTime::class
            || $typeName === DateTimeInterface::class
            || is_subclass_of($typeName, DateTimeInterface::class);
    }

    /**
     * Get the SchemaProperty attribute from a property, if present.
     *
     * @param ReflectionProperty $property The property to inspect.
     * @return SchemaProperty|null The attribute instance, or null if not present.
     */
    private static function getPropertyAttribute(ReflectionProperty $property): ?SchemaProperty
    {
        $attributes = $property->getAttributes(SchemaProperty::class);

        if ($attributes !== []) {
            return $attributes[0]->newInstance();
        }

        return null;
    }
}
