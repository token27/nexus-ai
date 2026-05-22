<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation\Rules;

use Attribute;

use function is_string;
use function preg_match;

use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;

/**
 * Validates that a string property matches a regex pattern.
 *
 * Usage:
 * ```php
 * #[Pattern('/^[A-Z]{2}[0-9]{4}$/')]
 * public string $code;
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Pattern implements ValidationRuleInterface
{
    /**
     * @param string $regex The regex pattern (including delimiters).
     */
    public function __construct(
        public readonly string $regex,
    ) {
    }

    /** {@inheritdoc} */
    public function validate(mixed $value, string $propertyName): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (preg_match($this->regex, $value) !== 1) {
            return "{$propertyName} does not match pattern {$this->regex}";
        }

        return null;
    }
}
