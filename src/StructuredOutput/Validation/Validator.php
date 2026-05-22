<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Validates a hydrated DTO instance by reading validation rule attributes.
 *
 * Iterates over all public properties, instantiates any attributes that
 * implement `ValidationRuleInterface`, and collects violation messages.
 *
 * Usage:
 * ```php
 * $violations = Validator::validate($articleDTO);
 * if ($violations !== []) {
 *     // handle errors
 * }
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface
 */
final class Validator
{
    /**
     * Validate an object by executing all validation rule attributes.
     *
     * @param object $instance The hydrated DTO instance to validate.
     * @return array<int, string> Array of violation messages. Empty if valid.
     * @throws ReflectionException If reflection on the object fails.
     */
    public static function validate(object $instance): array
    {
        $reflection = new ReflectionClass($instance);
        $violations = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes();

            if ($attributes === []) {
                continue;
            }

            $propertyName = $property->getName();
            $value = $property->isInitialized($instance)
                ? $property->getValue($instance)
                : null;

            foreach ($attributes as $attribute) {
                $ruleInstance = $attribute->newInstance();

                if (!$ruleInstance instanceof ValidationRuleInterface) {
                    continue;
                }

                $error = $ruleInstance->validate($value, $propertyName);

                if ($error !== null) {
                    $violations[] = $error;
                }
            }
        }

        return $violations;
    }
}
