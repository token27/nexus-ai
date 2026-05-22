<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation\Rules;

use Attribute;

use function is_string;
use function strlen;

use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;

/**
 * Validates that a string property meets a minimum length.
 *
 * Usage:
 * ```php
 * #[MinLength(10)]
 * public string $title;
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class MinLength implements ValidationRuleInterface
{
    /**
     * @param int $min Minimum allowed length.
     */
    public function __construct(
        public readonly int $min,
    ) {
    }

    /** {@inheritdoc} */
    public function validate(mixed $value, string $propertyName): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (strlen($value) < $this->min) {
            return "{$propertyName} must be at least {$this->min} characters";
        }

        return null;
    }
}
