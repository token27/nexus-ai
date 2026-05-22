<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Event;

use Token27\NexusAI\Contract\StreamChunkInterface;

/**
 * Event emitted each time a streaming chunk is received.
 *
 * Contains the chunk data and its sequential index for ordering
 * and progress tracking.
 *
 * @see \Token27\NexusAI\Observability\EventBus
 * @see \Token27\NexusAI\Contract\StreamChunkInterface
 */
final readonly class StreamChunkReceived
{
    /**
     * @param StreamChunkInterface $chunk The received stream chunk.
     * @param int $index The sequential index of this chunk (0-based).
     */
    public function __construct(
        public StreamChunkInterface $chunk,
        public int $index,
    ) {
    }
}
