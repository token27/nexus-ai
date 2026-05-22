<?php

declare(strict_types=1);

namespace Token27\NexusAI\Stream;

use Token27\NexusAI\Enum\FinishReason;

/**
 * Chunk containing a fragment of generated text (delta).
 *
 * Each TextChunk carries a piece of the LLM's output as it streams.
 * The consumer concatenates all TextChunk texts to reconstruct the
 * complete response.
 *
 * Example sequence:
 *   TextChunk("Hello") → TextChunk(" world") → TextChunk("!", finishReason: Stop)
 *
 * @see StreamChunk
 * @see \Token27\NexusAI\Response\StreamResponse::collect()
 */
final readonly class TextChunk extends StreamChunk
{
    /**
     * @param string $text The text fragment. E.g.: "Hello", " world", "!".
     * @param int $index Choice index. Default 0.
     * @param FinishReason|null $finishReason Null for intermediate chunks, value for the last.
     */
    public function __construct(
        public string $text,
        int $index = 0,
        ?FinishReason $finishReason = null,
    ) {
        parent::__construct(
            type: 'text_delta',
            index: $index,
            finishReason: $finishReason,
        );
    }

    /**
     * Returns the text fragment of this chunk.
     *
     * @return string The text delta content.
     */
    public function getText(): string
    {
        return $this->text;
    }
}
