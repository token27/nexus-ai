<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\OpenAI;

use Generator;
use Psr\Http\Message\StreamInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Exception\StreamException;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Stream\TextChunk;
use Token27\NexusAI\Stream\ToolCallChunk;
use Token27\NexusAI\Stream\UsageChunk;

/**
 * Parses OpenAI Server-Sent Events (SSE) streams into typed StreamChunk objects.
 *
 * Reads the PSR-7 response body line-by-line, parsing SSE events:
 * - Lines starting with "data: " contain JSON payloads
 * - "data: [DONE]" signals end of stream
 * - Empty lines and comment lines (starting with ':') are skipped
 *
 * Yields typed chunk objects implementing StreamChunkInterface:
 * - {@see TextChunk} for text content deltas
 * - {@see ToolCallChunk} for tool call deltas
 * - {@see UsageChunk} for token usage information
 *
 * Inspired by:
 * - Prism → OpenAITextHandler::stream() with decodeStreamedResponse()
 * - Neuron AI → HandleChat::stream() with getStreamIterator()
 *
 * @see \Token27\NexusAI\Contract\StreamChunkInterface
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIDriver::stream()
 */
final class OpenAIStreamParser
{
    /**
     * Parses an SSE stream body and yields typed StreamChunk objects.
     *
     * Flow for each line:
     * 1. Skip empty lines (SSE event separators)
     * 2. Skip comment lines (starting with ':')
     * 3. Extract data after "data: " prefix
     * 4. If data is "[DONE]" → end the generator
     * 5. JSON decode the data
     * 6. Yield appropriate chunk based on content:
     *    - delta.content present → TextChunk
     *    - delta.tool_calls present → ToolCallChunk (one per tool call)
     *    - usage present → UsageChunk
     *    - finish_reason only → TextChunk with empty text
     *
     * @param StreamInterface $body The PSR-7 stream body to parse.
     * @return Generator<int, StreamChunkInterface> Generator yielding stream chunks.
     *
     * @throws StreamException On JSON parse errors.
     */
    public static function parse(StreamInterface $body): Generator
    {
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            $buffer .= $chunk;

            // Process complete lines from the buffer
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                // Remove trailing CR if present (CRLF line endings)
                $line = rtrim($line, "\r");

                // Skip empty lines (SSE event separators)
                if ($line === '') {
                    continue;
                }

                // Skip SSE comment lines
                if (str_starts_with($line, ':')) {
                    continue;
                }

                // Only process "data: " prefixed lines
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                // Extract the data payload
                $data = substr($line, 6); // strlen('data: ') === 6

                // Check for stream termination signal
                if ($data === '[DONE]') {
                    return;
                }

                // Parse the JSON payload
                $decoded = json_decode($data, true);
                if (!is_array($decoded)) {
                    throw new StreamException(
                        "Failed to parse SSE data: {$data}",
                    );
                }

                // Extract delta and choice info
                $choice = $decoded['choices'][0] ?? [];
                $delta = $choice['delta'] ?? [];
                $finishReason = isset($choice['finish_reason'])
                    ? OpenAIResponseParser::mapFinishReason($choice['finish_reason'])
                    : null;
                $index = $choice['index'] ?? 0;

                // Text content delta
                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield new TextChunk(
                        text: $delta['content'],
                        index: $index,
                        finishReason: $finishReason,
                    );

                    continue;
                }

                // Tool call delta — one ToolCallChunk per tool_calls entry
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tcDelta) {
                        yield new ToolCallChunk(
                            toolCallId: $tcDelta['id'] ?? null,
                            toolName: $tcDelta['function']['name'] ?? null,
                            argumentsDelta: $tcDelta['function']['arguments'] ?? '',
                            toolCallIndex: $tcDelta['index'] ?? 0,
                            index: $index,
                            finishReason: $finishReason,
                        );
                    }

                    continue;
                }

                // Usage info (typically the last chunk with stream_options.include_usage)
                if (isset($decoded['usage'])) {
                    $usage = new Usage(
                        textInputTokens: $decoded['usage']['prompt_tokens'] ?? 0,
                        textOutputTokens: $decoded['usage']['completion_tokens'] ?? 0,
                    );

                    yield new UsageChunk(
                        usage: $usage,
                        index: $index,
                        finishReason: $finishReason,
                    );

                    continue;
                }

                // Finish reason only (no content delta) — emit as empty TextChunk
                if ($finishReason !== null) {
                    yield new TextChunk(
                        text: '',
                        index: $index,
                        finishReason: $finishReason,
                    );
                }
            }
        }
    }
}
