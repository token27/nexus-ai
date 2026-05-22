<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation\Rules;

use Attribute;
use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;

/**
 * Validates that a property is not null, empty string, or empty array.
 *
 * Usage:
 * ```php
 * #[Required]
 * public string $title;
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Required implements ValidationRuleInterface
{
    /** {@inheritdoc} */
    public function validate(mixed $value, string $propertyName): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return "{$propertyName} is required";
        }

        return null;
    }
}
