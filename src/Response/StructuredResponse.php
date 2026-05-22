<?php

declare(strict_types=1);

namespace Token27\NexusAI\Response;

use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Result of a structured output request.
 *
 * Contains the deserialized DTO instance along with the raw JSON text
 * used for debugging, and standard response metadata.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface::structured()
 * @see \Token27\NexusAI\Request\StructuredRequest
 */
final readonly class StructuredResponse implements ResponseInterface
{
    /**
     * @param object $object The deserialized DTO instance. E.g., if outputClass was ArticleDTO::class, this is an ArticleDTO instance.
     * @param string $text Raw JSON text from which the object was extracted. Useful for debugging.
     * @param FinishReason $finishReason Reason why the LLM stopped generating.
     * @param Usage $usage Token usage statistics.
     * @param Meta $meta Response metadata (ID, model, rate limits).
     * @param array<string, mixed>|null $raw Raw JSON response from the provider. For debugging.
     * @param int $attempts Number of attempts made to get valid structured output.
     * @param array<string> $violations Validation violations from the last attempt (empty if valid).
     */
    public function __construct(
        public object $object,
        public string $text,
        public FinishReason $finishReason,
        public Usage $usage = new Usage(),
        public Meta $meta = new Meta(),
        public ?array $raw = null,
        public int $attempts = 1,
        public array $violations = [],
    ) {
    }

    /** {@inheritdoc} */
    public function getUsage(): Usage
    {
        return $this->usage;
    }

    /** {@inheritdoc} */
    public function getMeta(): Meta
    {
        return $this->meta;
    }

    /** {@inheritdoc} */
    public function getFinishReason(): FinishReason
    {
        return $this->finishReason;
    }

    /** {@inheritdoc} */
    public function getRaw(): ?array
    {
        return $this->raw;
    }
}
