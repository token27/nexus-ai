<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation;

/**
 * Contract for validation rules applied to DTO properties via PHP Attributes.
 *
 * Each rule is a `#[Attribute(TARGET_PROPERTY)]` that implements this interface.
 * The Validator reads these attributes via reflection and invokes `validate()`
 * on each one to collect violation messages.
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
interface ValidationRuleInterface
{
    /**
     * Validate a property value.
     *
     * @param mixed $value The current value of the property.
     * @param string $propertyName The property name (for error messages).
     * @return string|null Null if valid, error message string if invalid.
     */
    public function validate(mixed $value, string $propertyName): ?string;
}
