<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput;

use function array_key_exists;

use BackedEnum;

use function class_exists;

use DateTimeImmutable;

use function enum_exists;

use Exception;

use function gettype;
use function is_array;
use function is_numeric;
use function is_string;
use function is_subclass_of;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Token27\NexusAI\Exception\StructuredOutputException;

/**
 * Deserializes a JSON string into a typed PHP DTO instance.
 *
 * Uses `ReflectionClass::newInstanceWithoutConstructor()` + `ReflectionProperty::setValue()`
 * to hydrate objects without calling the constructor, supporting readonly properties.
 *
 * Handles: primitives, nullable, arrays (typed via SchemaProperty), BackedEnum,
 * nested classes, DateTimeImmutable/DateTime.
 *
 * @see \Token27\NexusAI\StructuredOutput\SchemaProperty
 */
final class Deserializer
{
    /**
     * Deserialize a JSON string into a typed DTO instance.
     *
     * @param string $json Valid JSON string.
     * @param string $className FQCN of the target DTO class.
     * @return object The hydrated DTO instance.
     * @throws StructuredOutputException If JSON is invalid or class does not exist.
     */
    public static function deserialize(string $json, string $className): object
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new StructuredOutputException(
                'Invalid JSON: ' . $e->getMessage(),
                responseText: $json,
                schema: [],
            );
        }

        if (!is_array($data)) {
            throw new StructuredOutputException(
                'JSON must decode to an object/array',
                responseText: $json,
                schema: [],
            );
        }

        return self::hydrateObject($data, $className);
    }

    /**
     * Hydrate an object from an associative array.
     *
     * @param array<string, mixed> $data The decoded JSON data.
     * @param string $className FQCN of the target class.
     * @return object The hydrated instance.
     * @throws StructuredOutputException
     */
    private static function hydrateObject(array $data, string $className): object
    {
        if (!class_exists($className)) {
            throw new StructuredOutputException(
                "Class {$className} does not exist",
                responseText: '',
                schema: [],
            );
        }

        try {
            $reflection = new ReflectionClass($className);
            $instance = $reflection->newInstanceWithoutConstructor();
        } catch (ReflectionException $e) {
            throw new StructuredOutputException(
                "Cannot instantiate {$className}: " . $e->getMessage(),
                responseText: '',
                schema: [],
            );
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $data)) {
                // If property has a default value, skip — it already has one
                // If not, leave uninitialized (will error on access for typed props)
                continue;
            }

            $value = $data[$propertyName];
            $type = $property->getType();

            if ($type !== null) {
                $value = self::castValue($value, $type, $property);
            }

            $property->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * Cast a value based on the property's type declaration.
     *
     * @param mixed $value The raw value from JSON.
     * @param \ReflectionType $type The property's type.
     * @param ReflectionProperty $property The property (for attribute reading).
     * @return mixed The correctly typed value.
     * @throws StructuredOutputException
     */
    private static function castValue(mixed $value, \ReflectionType $type, ReflectionProperty $property): mixed
    {
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if (!($unionType instanceof ReflectionNamedType)) {
                    continue;
                }

                try {
                    return self::castToSingleType($value, $unionType, $property);
                } catch (\Throwable) {
                    continue;
                }
            }

            throw new StructuredOutputException(
                "Cannot cast value to any type in union for property {$property->getName()}",
                responseText: '',
                schema: [],
            );
        }

        if ($type instanceof ReflectionNamedType) {
            return self::castToSingleType($value, $type, $property);
        }

        return $value;
    }

    /**
     * Cast a value to a single named type.
     *
     * @param mixed $value The raw value.
     * @param ReflectionNamedType $type The target type.
     * @param ReflectionProperty $property The property for context.
     * @return mixed The typed value.
     * @throws StructuredOutputException
     */
    private static function castToSingleType(
        mixed $value,
        ReflectionNamedType $type,
        ReflectionProperty $property,
    ): mixed {
        $typeName = $type->getName();

        // Handle null values
        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }

            throw new StructuredOutputException(
                "Property {$property->getName()} does not allow null values",
                responseText: '',
                schema: [],
            );
        }

        return match ($typeName) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'array' => self::handleArray($value, $property),
            'DateTimeImmutable' => self::createDateTimeImmutable($value),
            'DateTime' => self::createDateTime($value),
            default => self::handleObject($value, $typeName),
        };
    }

    /**
     * Handle array deserialization. If SchemaProperty has type hints, deserialize items.
     *
     * @param mixed $value The raw array value.
     * @param ReflectionProperty $property The property for attribute reading.
     * @return mixed The deserialized array.
     */
    private static function handleArray(mixed $value, ReflectionProperty $property): mixed
    {
        if (!is_array($value)) {
            return (array) $value;
        }

        $itemClass = self::getArrayItemClass($property);
        if ($itemClass === null) {
            return $value; // No type info, return as-is
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_array($item) && class_exists($itemClass)) {
                $result[$key] = self::hydrateObject($item, $itemClass);
            } elseif (enum_exists($itemClass) && is_subclass_of($itemClass, BackedEnum::class)) {
                $result[$key] = $itemClass::tryFrom($item) ?? $item;
            } else {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Resolves the array item class from SchemaProperty or PHPDoc.
     *
     * Resolution order:
     * 1. SchemaProperty $type attribute (explicit FQCN)
     * 2. PHPDoc @var ClassName[] annotation
     *
     * @param ReflectionProperty $property The property to inspect.
     * @return string|null The FQCN of the item class, or null if unresolvable.
     */
    private static function getArrayItemClass(ReflectionProperty $property): ?string
    {
        // 1. SchemaProperty $type
        $attributes = $property->getAttributes(SchemaProperty::class);
        if ($attributes !== []) {
            $attr = $attributes[0]->newInstance();
            if ($attr->type !== null) {
                return $attr->type;
            }
        }

        // 2. PHPDoc @var ClassName[]
        $docComment = $property->getDocComment();
        if ($docComment !== false && preg_match('/@var\s+([^\[\]\s]+)\[\]/', $docComment, $matches)) {
            return ltrim($matches[1], '\\');
        }

        return null;
    }

    /**
     * Handle object/enum deserialization.
     *
     * @param mixed $value The raw value.
     * @param string $typeName The target type name.
     * @return mixed The deserialized value.
     * @throws StructuredOutputException
     */
    private static function handleObject(mixed $value, string $typeName): mixed
    {
        // BackedEnum
        if (enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class)) {
            $enum = $typeName::tryFrom($value);
            if ($enum === null) {
                throw new StructuredOutputException(
                    "Invalid enum value '{$value}' for {$typeName}",
                    responseText: '',
                    schema: [],
                );
            }

            return $enum;
        }

        // Nested class
        if (is_array($value) && class_exists($typeName)) {
            return self::hydrateObject($value, $typeName);
        }

        return $value;
    }

    /**
     * Create a DateTimeImmutable from various formats.
     *
     * @param mixed $value The date value (string or numeric timestamp).
     * @throws StructuredOutputException
     */
    private static function createDateTimeImmutable(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                throw new StructuredOutputException(
                    "Cannot create DateTimeImmutable from: {$value}",
                    responseText: '',
                    schema: [],
                );
            }
        }

        if (is_numeric($value)) {
            return new DateTimeImmutable('@' . $value);
        }

        throw new StructuredOutputException(
            'Cannot create DateTimeImmutable from value type: ' . gettype($value),
            responseText: '',
            schema: [],
        );
    }

    /**
     * Create a DateTime from various formats.
     *
     * @param mixed $value The date value (string or numeric timestamp).
     * @throws StructuredOutputException
     */
    private static function createDateTime(mixed $value): \DateTime
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new \DateTime($value);
            } catch (Exception) {
                throw new StructuredOutputException(
                    "Cannot create DateTime from: {$value}",
                    responseText: '',
                    schema: [],
                );
            }
        }

        if (is_numeric($value)) {
            return new \DateTime('@' . $value);
        }

        throw new StructuredOutputException(
            'Cannot create DateTime from value type: ' . gettype($value),
            responseText: '',
            schema: [],
        );
    }
}
