<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput;

use Attribute;

/**
 * PHP Attribute to annotate DTO properties with JSON Schema metadata.
 *
 * Place this on public properties of your DTO class to control how
 * SchemaGenerator produces the JSON Schema sent to the LLM.
 *
 * Example:
 * ```php
 * class ArticleDTO {
 *     #[SchemaProperty(description: 'SEO-optimized article title', required: true)]
 *     public string $title;
 *
 *     #[SchemaProperty(description: 'Relevant tags', minItems: 1, maxItems: 5)]
 *     public array $tags;
 *
 *     #[SchemaProperty(description: 'Article status', enum: ['draft', 'review', 'published'])]
 *     public string $status;
 * }
 * ```
 *
 * @see \Token27\NexusAI\StructuredOutput\SchemaGenerator
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class SchemaProperty
{
    /**
     * @param string|null $description Description for the LLM. E.g., 'Title of the article, must be SEO-friendly'.
     * @param bool $required Whether the property is required in the output.
     * @param array<mixed>|null $enum Allowed values. E.g., ['draft', 'published', 'archived'].
     * @param string|null $format JSON Schema format. E.g., 'email', 'date-time', 'uri'.
     * @param string|null $pattern Regex pattern. E.g., '^[A-Z]{2}[0-9]{4}$'.
     * @param float|null $minimum Minimum numeric value.
     * @param float|null $maximum Maximum numeric value.
     * @param int|null $minItems Minimum number of array elements.
     * @param int|null $maxItems Maximum number of array elements.
     * @param mixed $default Default value for the property.
     * @param string|null $type FQCN for array item type. E.g., ArticleDTO::class for typed array deserialization.
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly bool $required = true,
        public readonly ?array $enum = null,
        public readonly ?string $format = null,
        public readonly ?string $pattern = null,
        public readonly ?float $minimum = null,
        public readonly ?float $maximum = null,
        public readonly ?int $minItems = null,
        public readonly ?int $maxItems = null,
        public readonly mixed $default = null,
        public readonly ?string $type = null,
    ) {
    }
}
