<?php

declare(strict_types=1);

namespace Token27\NexusAI\Stream;

use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;

/**
 * Chunk containing final token usage information.
 *
 * OpenAI sends this as the last SSE event when `stream_options.include_usage`
 * is set to `true`. Contains the complete token consumption for the request.
 *
 * This chunk is typically used by:
 * - {@see \Token27\NexusAI\Response\StreamResponse::collect()} to attach usage to the final TextResponse
 * - Cost tracking middleware to calculate the cost of a streamed request
 *
 * @see StreamChunk
 * @see Usage
 */
final readonly class UsageChunk extends StreamChunk
{
    /**
     * @param Usage $usage Complete token usage for the request.
     * @param int $index Choice index (normally 0).
     * @param FinishReason|null $finishReason Typically Stop for the final chunk.
     */
    public function __construct(
        public Usage $usage,
        int $index = 0,
        ?FinishReason $finishReason = null,
    ) {
        parent::__construct(
            type: 'usage',
            index: $index,
            finishReason: $finishReason,
        );
    }

    /**
     * Returns null — usage chunks do not carry text content.
     *
     * @return null Always null for usage chunks.
     */
    public function getText(): ?string
    {
        return null;
    }
}
