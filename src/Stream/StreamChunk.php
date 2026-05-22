<?php

declare(strict_types=1);

namespace Token27\NexusAI\Stream;

use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Enum\FinishReason;

/**
 * Abstract base class for all streaming chunk types.
 *
 * Provides the common structure shared by all chunk variants (text deltas,
 * tool call deltas, usage info). Concrete subclasses define the specific
 * content and type discriminator.
 *
 * The `type` field acts as a discriminator for consumers to identify what
 * kind of chunk they're processing without relying on instanceof checks:
 * - `'text_delta'`      → {@see TextChunk}
 * - `'tool_call_delta'` → {@see ToolCallChunk}
 * - `'usage'`           → {@see UsageChunk}
 *
 * @see StreamChunkInterface
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIStreamParser
 */
abstract readonly class StreamChunk implements StreamChunkInterface
{
    /**
     * @param string $type Discriminator: 'text_delta', 'tool_call_delta', 'tool_result', 'usage', 'error'.
     * @param int $index Choice index (normally 0).
     * @param FinishReason|null $finishReason Only present on the last chunk of a sequence.
     */
    public function __construct(
        protected string $type,
        protected int $index = 0,
        protected ?FinishReason $finishReason = null,
    ) {
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
}
