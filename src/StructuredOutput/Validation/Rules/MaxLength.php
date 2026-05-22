<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation\Rules;

use Attribute;

use function is_string;
use function strlen;

use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;

/**
 * Validates that a string property does not exceed a maximum length.
 *
 * Usage:
 * ```php
 * #[MaxLength(200)]
 * public string $title;
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class MaxLength implements ValidationRuleInterface
{
    /**
     * @param int $max Maximum allowed length.
     */
    public function __construct(
        public readonly int $max,
    ) {
    }

    /** {@inheritdoc} */
    public function validate(mixed $value, string $propertyName): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (strlen($value) > $this->max) {
            return "{$propertyName} must not exceed {$this->max} characters";
        }

        return null;
    }
}
