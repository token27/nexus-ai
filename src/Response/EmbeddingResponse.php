<?php

declare(strict_types=1);

namespace Token27\NexusAI\Response;

use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\ValueObject\Embedding;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Result of an embedding request.
 *
 * Contains one or more embedding vectors, one per input text.
 * The finishReason is always FinishReason::Stop for embedding requests.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface::embeddings()
 * @see \Token27\NexusAI\Request\EmbeddingRequest
 * @see \Token27\NexusAI\ValueObject\Embedding
 */
final readonly class EmbeddingResponse implements ResponseInterface
{
    /**
     * @param array<Embedding> $embeddings Array of embedding vectors, one per input text.
     * @param Usage $usage Token usage statistics.
     * @param Meta $meta Response metadata (ID, model, rate limits).
     * @param FinishReason $finishReason Always FinishReason::Stop for embeddings.
     * @param array<string, mixed>|null $raw Raw JSON response from the provider. For debugging.
     */
    public function __construct(
        public array $embeddings,
        public Usage $usage = new Usage(),
        public Meta $meta = new Meta(),
        public FinishReason $finishReason = FinishReason::Stop,
        public ?array $raw = null,
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
