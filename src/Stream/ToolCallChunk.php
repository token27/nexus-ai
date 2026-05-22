<?php

declare(strict_types=1);

namespace Token27\NexusAI\Stream;

use Token27\NexusAI\Enum\FinishReason;

/**
 * Chunk containing a fragment of a tool call delta.
 *
 * The LLM builds tool calls incrementally during streaming. Each ToolCallChunk
 * carries a piece of the tool call data:
 * - `toolCallId` and `toolName` arrive only in the first chunk of each tool call.
 * - `argumentsDelta` contains a fragment of the JSON arguments string.
 *
 * The consumer must accumulate `argumentsDelta` across all chunks to reconstruct
 * the complete JSON arguments:
 *
 * ```php
 * $toolCallId = null;
 * $toolName = null;
 * $argsBuffer = '';
 *
 * foreach ($stream as $chunk) {
 *     if ($chunk instanceof ToolCallChunk) {
 *         $toolCallId ??= $chunk->toolCallId;
 *         $toolName ??= $chunk->toolName;
 *         $argsBuffer .= $chunk->argumentsDelta;
 *     }
 * }
 * // $argsBuffer now contains the complete JSON arguments
 * ```
 *
 * @see StreamChunk
 * @see \Token27\NexusAI\Response\StreamResponse::collect()
 */
final readonly class ToolCallChunk extends StreamChunk
{
    /**
     * @param string|null $toolCallId ID of the tool call. Only present in the first chunk.
     * @param string|null $toolName Name of the tool. Only present in the first chunk.
     * @param string $argumentsDelta Fragment of the JSON arguments string. Accumulate across chunks.
     * @param int $toolCallIndex Index of the tool call (for parallel tool calls).
     * @param int $index Choice index.
     * @param FinishReason|null $finishReason Null until the last chunk.
     */
    public function __construct(
        public ?string $toolCallId,
        public ?string $toolName,
        public string $argumentsDelta,
        public int $toolCallIndex = 0,
        int $index = 0,
        ?FinishReason $finishReason = null,
    ) {
        parent::__construct(
            type: 'tool_call_delta',
            index: $index,
            finishReason: $finishReason,
        );
    }

    /**
     * Returns null — tool call chunks do not carry text content.
     *
     * @return null Always null for tool call chunks.
     */
    public function getText(): ?string
    {
        return null;
    }
}
