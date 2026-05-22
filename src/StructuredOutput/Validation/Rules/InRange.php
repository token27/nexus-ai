<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput\Validation\Rules;

use Attribute;

use function is_numeric;

use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;

/**
 * Validates that a numeric property is within a given range.
 *
 * Either `$min` or `$max` (or both) can be specified.
 *
 * Usage:
 * ```php
 * #[InRange(min: 100, max: 5000)]
 * public int $wordCount;
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class InRange implements ValidationRuleInterface
{
    /**
     * @param float|null $min Minimum allowed value (inclusive).
     * @param float|null $max Maximum allowed value (inclusive).
     */
    public function __construct(
        public readonly ?float $min = null,
        public readonly ?float $max = null,
    ) {
    }

    /** {@inheritdoc} */
    public function validate(mixed $value, string $propertyName): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numericValue = (float) $value;

        if ($this->min !== null && $numericValue < $this->min) {
            $maxLabel = $this->max !== null ? (string) $this->max : '∞';

            return "{$propertyName} must be between {$this->min} and {$maxLabel}";
        }

        if ($this->max !== null && $numericValue > $this->max) {
            $minLabel = $this->min !== null ? (string) $this->min : '-∞';

            return "{$propertyName} must be between {$minLabel} and {$this->max}";
        }

        return null;
    }
}
