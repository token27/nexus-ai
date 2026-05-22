<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Enum\FinishReason;

/**
 * Contract for each fragment emitted during streaming via Generator.
 *
 * Each chunk represents a delta of the LLM response as it is being generated.
 * The type field distinguishes between text deltas, tool call deltas, usage info, etc.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface::stream()
 */
interface StreamChunkInterface
{
    /**
     * Returns the text delta of this chunk.
     *
     * @return string|null The text content, or null if this is a non-text chunk (tool call, usage, etc).
     */
    public function getText(): ?string;

    /**
     * Returns the type of this chunk.
     *
     * Known types: 'text_delta', 'tool_call_delta', 'tool_result', 'usage', 'error'.
     *
     * @return string The chunk type identifier.
     */
    public function getType(): string;

    /**
     * Returns the choice index (normally 0).
     *
     * Allows for multiple simultaneous completions.
     *
     * @return int The choice index.
     */
    public function getIndex(): int;

    /**
     * Returns the finish reason, only present on the last chunk.
     *
     * @return FinishReason|null The finish reason, or null for non-final chunks.
     */
    public function getFinishReason(): ?FinishReason;
}
