<?php

declare(strict_types=1);

namespace Token27\NexusAI\Stream\Adapter;

use Generator;
use Token27\NexusAI\Contract\StreamChunkInterface;

/**
 * Contract for adapters that transform streaming chunks into protocol-specific output.
 *
 * Each adapter converts a Generator of {@see StreamChunkInterface} objects into
 * a Generator of formatted strings suitable for a specific output protocol:
 * - SSE (Server-Sent Events) for HTTP streaming → {@see SSEAdapter}
 * - AG-UI for frontend frameworks
 * - WebSocket frames
 * - Custom formats
 *
 * Inspired by Neuron AI's `src/AgUI/` adapter pattern.
 *
 * @see SSEAdapter
 * @see StreamChunkInterface
 */
interface StreamAdapterInterface
{
    /**
     * Transforms stream chunks into protocol-formatted strings.
     *
     * @param Generator<int, StreamChunkInterface> $chunks Generator yielding StreamChunkInterface objects.
     * @return Generator<int, string> Generator yielding formatted strings for the target protocol.
     */
    public function adapt(Generator $chunks): Generator;

    /**
     * Returns the MIME content type for this adapter's output format.
     *
     * @return string The MIME type (e.g., 'text/event-stream' for SSE).
     */
    public function getContentType(): string;
}
