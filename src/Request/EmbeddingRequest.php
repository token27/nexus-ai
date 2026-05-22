<?php

declare(strict_types=1);

namespace Token27\NexusAI\Request;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Message\Message;

/**
 * Value Object for embedding requests.
 *
 * Generates numerical vector representations of text(s).
 * Simpler than TextRequest — no messages, tools, or temperature.
 *
 * Supports both single-text and batch-text embedding:
 * - Single: 'Hello world'
 * - Batch:  ['Hello world', 'Another text', ...]
 *
 * @see \Token27\NexusAI\Contract\DriverInterface::embeddings()
 * @see \Token27\NexusAI\ValueObject\Embedding
 */
final readonly class EmbeddingRequest implements RequestInterface
{
    /**
     * @param string $provider Provider key (e.g., 'openai').
     * @param string $model Embedding model (e.g., 'text-embedding-3-small').
     * @param string|array<string> $input Text(s) to embed. String for single, array for batch.
     * @param int|null $dimensions Vector dimensions. Only some models support this.
     * @param array<string, mixed> $options Passthrough provider-specific options.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string|array $input,
        public ?int $dimensions = null,
        public array $options = [],
    ) {
    }

    /** {@inheritdoc} */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /** {@inheritdoc} */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Embedding requests don't use conversation messages.
     *
     * @return array<Message> Always returns an empty array.
     */
    public function getMessages(): array
    {
        return [];
    }

    /** {@inheritdoc} */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Embedding requests don't use max tokens.
     *
     * @return int|null Always returns null.
     */
    public function getMaxTokens(): ?int
    {
        return null;
    }

    /**
     * Embedding requests don't use temperature.
     *
     * @return float|null Always returns null.
     */
    public function getTemperature(): ?float
    {
        return null;
    }
}
