<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Anthropic;

use Generator;
use Psr\Http\Message\StreamInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Exception\StreamException;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Stream\TextChunk;
use Token27\NexusAI\Stream\ToolCallChunk;
use Token27\NexusAI\Stream\UsageChunk;

/**
 * Parses Anthropic SSE streams into typed StreamChunk objects.
 *
 * Anthropic uses typed events (very different from OpenAI):
 * - event: message_start     → contains input token usage
 * - event: content_block_start → signals new content block
 * - event: content_block_delta → text or tool call argument fragments
 * - event: content_block_stop  → signals end of content block
 * - event: message_delta     → contains stop_reason and output tokens
 * - event: message_stop      → end of stream
 *
 * @see \Token27\NexusAI\Driver\Anthropic\AnthropicDriver::stream()
 */
final class AnthropicStreamParser
{
    /**
     * Parses an Anthropic SSE stream and yields typed StreamChunk objects.
     *
     * Reads pairs of `event:` and `data:` lines. Uses the event type
     * to determine how to parse the data payload.
     *
     * @param StreamInterface $body The PSR-7 stream body to parse.
     * @return Generator<int, StreamChunkInterface> Generator yielding stream chunks.
     *
     * @throws StreamException On JSON parse errors.
     */
    public static function parse(StreamInterface $body): Generator
    {
        $buffer = '';
        $currentEvent = '';
        $inputTokens = 0;
        $outputTokens = 0;
        // Track active tool_use blocks by content block index
        /** @var array<int, array{id: string|null, name: string|null}> $activeToolBlocks */
        $activeToolBlocks = [];

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);

                // Empty line = end of SSE event
                if ($line === '') {
                    $currentEvent = '';
                    continue;
                }

                // SSE comment
                if (str_starts_with($line, ':')) {
                    continue;
                }

                // Capture event type
                if (str_starts_with($line, 'event: ')) {
                    $currentEvent = substr($line, 7);
                    continue;
                }

                // Process data lines
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);
                $decoded = json_decode($data, true);

                if (!is_array($decoded)) {
                    throw new StreamException("Failed to parse Anthropic SSE data: {$data}");
                }

                // Handle based on event type
                yield from self::processEvent(
                    $currentEvent,
                    $decoded,
                    $inputTokens,
                    $outputTokens,
                    $activeToolBlocks,
                );

                // Check for message_stop to end
                if ($currentEvent === 'message_stop') {
                    return;
                }
            }
        }
    }

    /**
     * Processes a single Anthropic SSE event and yields appropriate chunks.
     *
     * @param string $eventType The SSE event type.
     * @param array<string, mixed> $data Decoded JSON data.
     * @param int &$inputTokens Running input token count.
     * @param int &$outputTokens Running output token count.
     * @param array<int, array{id: string|null, name: string|null}> &$activeToolBlocks Active tool blocks.
     * @param-out array<int, array{id: string|null, name: string|null}> $activeToolBlocks
     * @return Generator<int, StreamChunkInterface>
     */
    private static function processEvent(
        string $eventType,
        array $data,
        int &$inputTokens,
        int &$outputTokens,
        array &$activeToolBlocks,
    ): Generator {
        switch ($eventType) {
            case 'message_start':
                // Extract input tokens from the initial message
                $inputTokens = $data['message']['usage']['input_tokens'] ?? 0;
                break;

            case 'content_block_start':
                $index = $data['index'] ?? 0;
                $block = $data['content_block'] ?? [];

                if (($block['type'] ?? '') === 'tool_use') {
                    /** @var string|null $blockId */
                    $blockId = $block['id'] ?? null;
                    /** @var string|null $blockName */
                    $blockName = $block['name'] ?? null;
                    $activeToolBlocks[$index] = [
                        'id' => $blockId,
                        'name' => $blockName,
                    ];
                    // Yield initial tool call chunk with id and name
                    yield new ToolCallChunk(
                        toolCallId: $block['id'] ?? null,
                        toolName: $block['name'] ?? null,
                        argumentsDelta: '',
                        toolCallIndex: $index,
                        index: 0,
                    );
                }
                break;

            case 'content_block_delta':
                $index = $data['index'] ?? 0;
                $delta = $data['delta'] ?? [];
                $deltaType = $delta['type'] ?? '';

                if ($deltaType === 'text_delta') {
                    yield new TextChunk(
                        text: $delta['text'] ?? '',
                        index: 0,
                    );
                } elseif ($deltaType === 'input_json_delta') {
                    yield new ToolCallChunk(
                        toolCallId: null,
                        toolName: null,
                        argumentsDelta: $delta['partial_json'] ?? '',
                        toolCallIndex: $index,
                        index: 0,
                    );
                }
                break;

            case 'content_block_stop':
                $index = $data['index'] ?? 0;
                unset($activeToolBlocks[$index]);
                break;

            case 'message_delta':
                $delta = $data['delta'] ?? [];
                $outputTokens = $data['usage']['output_tokens'] ?? $outputTokens;
                $stopReason = $delta['stop_reason'] ?? null;
                $finishReason = AnthropicResponseParser::mapFinishReason($stopReason);

                yield new UsageChunk(
                    usage: new Usage(
                        textInputTokens: $inputTokens,
                        textOutputTokens: $outputTokens,
                    ),
                    index: 0,
                    finishReason: $finishReason,
                );
                break;

            case 'message_stop':
                // End of stream — handled by caller
                break;
        }
    }
}
