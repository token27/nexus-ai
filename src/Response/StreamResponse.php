<?php

declare(strict_types=1);

namespace Token27\NexusAI\Response;

use Generator;
use IteratorAggregate;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Stream\TextChunk;
use Token27\NexusAI\Stream\ToolCallChunk;
use Token27\NexusAI\Stream\UsageChunk;
use Token27\NexusAI\ValueObject\ToolCall;
use Traversable;

/**
 * Wrapper over a Generator of stream chunks with convenience methods.
 *
 * Provides three ways to consume a stream:
 *
 * 1. **Iterate** — via `foreach` (implements IteratorAggregate):
 *    ```php
 *    foreach ($streamResponse as $chunk) {
 *        // Process each StreamChunkInterface
 *    }
 *    ```
 *
 * 2. **Collect** — consume the entire stream into a TextResponse:
 *    ```php
 *    $textResponse = $streamResponse->collect();
 *    echo $textResponse->text; // Complete concatenated text
 *    ```
 *
 * 3. **Text only** — generator that emits only text strings:
 *    ```php
 *    foreach ($streamResponse->text() as $textFragment) {
 *        echo $textFragment; // Only string fragments, no tool calls/usage
 *    }
 *    ```
 *
 * @implements IteratorAggregate<int, StreamChunkInterface>
 *
 * @see TextResponse
 * @see \Token27\NexusAI\Contract\DriverInterface::stream()
 */
final class StreamResponse implements IteratorAggregate
{
    /**
     * @param Generator<int, StreamChunkInterface> $chunks The generator of StreamChunks from the driver.
     */
    public function __construct(
        private readonly Generator $chunks,
    ) {
    }

    /**
     * Returns the chunk generator for foreach iteration.
     *
     * @return Traversable<int, StreamChunkInterface> The stream chunk generator.
     */
    public function getIterator(): Traversable
    {
        return $this->chunks;
    }

    /**
     * Consumes the entire stream and returns a complete TextResponse.
     *
     * Concatenates all text deltas, accumulates tool calls, captures
     * the final usage and finish reason. Useful when you want streaming
     * (for progress) but also need the complete response object at the end.
     *
     * **Warning**: This consumes the generator — it can only be called once.
     *
     * @return TextResponse The complete response reconstructed from all chunks.
     */
    public function collect(): TextResponse
    {
        $text = '';
        $usage = null;
        $finishReason = null;

        /**
         * Tool call accumulator.
         * Keyed by toolCallIndex, each entry tracks the accumulated state.
         *
         * @var array<int, array{id: string|null, name: string|null, arguments: string}> $toolCallAccumulator
         */
        $toolCallAccumulator = [];

        foreach ($this->chunks as $chunk) {
            // Accumulate text deltas
            if ($chunk instanceof TextChunk) {
                $text .= $chunk->text;
            }

            // Accumulate tool call deltas
            if ($chunk instanceof ToolCallChunk) {
                $idx = $chunk->toolCallIndex;

                if (!isset($toolCallAccumulator[$idx])) {
                    $toolCallAccumulator[$idx] = [
                        'id' => null,
                        'name' => null,
                        'arguments' => '',
                    ];
                }

                $toolCallAccumulator[$idx]['id'] ??= $chunk->toolCallId;
                $toolCallAccumulator[$idx]['name'] ??= $chunk->toolName;
                $toolCallAccumulator[$idx]['arguments'] .= $chunk->argumentsDelta;
            }

            // Capture usage from the final UsageChunk
            if ($chunk instanceof UsageChunk) {
                $usage = $chunk->usage;
            }

            // Track the latest finish reason
            if ($chunk->getFinishReason() !== null) {
                $finishReason = $chunk->getFinishReason();
            }
        }

        // Build ToolCall value objects from accumulated data
        /** @var array<ToolCall> $toolCalls */
        $toolCalls = [];
        foreach ($toolCallAccumulator as $tc) {
            /** @var array<string, mixed> $parsedArgs */
            $parsedArgs = json_decode($tc['arguments'], true) ?? [];

            $toolCalls[] = new ToolCall(
                id: $tc['id'] ?? '',
                name: $tc['name'] ?? '',
                arguments: $parsedArgs,
            );
        }

        return new TextResponse(
            text: $text,
            finishReason: $finishReason ?? FinishReason::Unknown,
            toolCalls: $toolCalls,
            usage: $usage ?? new Usage(),
        );
    }

    /**
     * Generator that emits only text strings from the stream.
     *
     * Filters out tool call chunks, usage chunks, and any other non-text
     * chunks. Only yields the string content from TextChunk instances.
     *
     * **Warning**: This consumes the generator — it can only be called once.
     *
     * @return Generator<int, string> Generator yielding text fragment strings.
     */
    public function text(): Generator
    {
        foreach ($this->chunks as $chunk) {
            if ($chunk instanceof TextChunk) {
                yield $chunk->text;
            }
        }
    }
}
