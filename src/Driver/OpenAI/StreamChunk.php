<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\OpenAI;

use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;

/**
 * Concrete implementation of StreamChunkInterface for OpenAI stream events.
 *
 * Represents a single chunk (delta) received from the OpenAI streaming API.
 * Contains text delta, tool call delta, usage info, or finish reason.
 *
 * @see \Token27\NexusAI\Contract\StreamChunkInterface
 * @internal Used by OpenAIStreamParser. Will be moved to Stream/ namespace in Phase 5.
 */
final readonly class StreamChunk implements StreamChunkInterface
{
    /**
     * @param string|null $text Text delta, or null for non-text chunks.
     * @param string $type Chunk type: 'text_delta', 'tool_call_delta', 'usage', 'finish'.
     * @param int $index Choice index (normally 0).
     * @param FinishReason|null $finishReason Finish reason, only present on last chunk.
     * @param array<string, mixed>|null $toolCallDelta Raw tool call delta data from OpenAI.
     * @param Usage|null $usage Token usage, only present on final usage chunk.
     */
    public function __construct(
        private ?string $text,
        private string $type,
        private int $index = 0,
        private ?FinishReason $finishReason = null,
        private ?array $toolCallDelta = null,
        private ?Usage $usage = null,
    ) {
    }

    /** {@inheritdoc} */
    public function getText(): ?string
    {
        return $this->text;
    }

    /** {@inheritdoc} */
    public function getType(): string
    {
        return $this->type;
    }

    /** {@inheritdoc} */
    public function getIndex(): int
    {
        return $this->index;
    }

    /** {@inheritdoc} */
    public function getFinishReason(): ?FinishReason
    {
        return $this->finishReason;
    }

    /**
     * Returns the raw tool call delta data from OpenAI.
     *
     * @return array<string, mixed>|null The tool call delta, or null.
     */
    public function getToolCallDelta(): ?array
    {
        return $this->toolCallDelta;
    }

    /**
     * Returns the token usage data (final chunk only).
     *
     * @return Usage|null The usage data, or null.
     */
    public function getUsage(): ?Usage
    {
        return $this->usage;
    }
}
