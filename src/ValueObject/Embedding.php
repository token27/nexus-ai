<?php

declare(strict_types=1);

namespace Token27\NexusAI\ValueObject;

/**
 * A single embedding vector.
 *
 * Represents the numerical vector representation of a text input.
 * The dimension depends on the model (e.g., 1536 for text-embedding-3-small).
 *
 * @see \Token27\NexusAI\Contract\DriverInterface::embeddings()
 */
readonly class Embedding
{
    /**
     * @param array<float> $values Embedding vector (e.g., [0.0023, -0.0045, 0.0067, ...]).
     * @param int $index Index of the input this embedding corresponds to. 0 for single-text requests.
     */
    public function __construct(
        public array $values,
        public int $index,
    ) {
    }
}
