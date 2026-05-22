<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Gemini;

use Generator;
use Psr\Http\Message\StreamInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Exception\StreamException;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Stream\TextChunk;
use Token27\NexusAI\Stream\ToolCallChunk;
use Token27\NexusAI\Stream\UsageChunk;

/**
 * Parses Gemini SSE streams into typed StreamChunk objects.
 *
 * Gemini streaming uses the `streamGenerateContent?alt=sse` endpoint.
 * Each SSE event contains a partial response with the same structure
 * as generateContent but with incremental content.
 *
 * Unlike Anthropic's typed events, Gemini uses standard SSE `data:` lines
 * with the same JSON structure as the non-streaming response.
 *
 * @see \Token27\NexusAI\Driver\Gemini\GeminiDriver::stream()
 */
final class GeminiStreamParser
{
    /**
     * Parses a Gemini SSE stream and yields typed StreamChunk objects.
     *
     * Each SSE data line contains a partial generateContent response.
     * Text parts are yielded as TextChunk, functionCall parts as ToolCallChunk.
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

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);

                // Skip empty lines and comments
                if ($line === '' || str_starts_with($line, ':')) {
                    continue;
                }

                // Only process data lines
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);

                // Gemini doesn't use [DONE] but check anyway
                if ($data === '[DONE]') {
                    return;
                }

                $decoded = json_decode($data, true);
                if (!is_array($decoded)) {
                    throw new StreamException("Failed to parse Gemini SSE data: {$data}");
                }

                $candidate = $decoded['candidates'][0] ?? [];
                $parts = $candidate['content']['parts'] ?? [];
                $finishReason = isset($candidate['finishReason'])
                    ? GeminiResponseParser::mapFinishReason($candidate['finishReason'])
                    : null;

                // Process each part
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        yield new TextChunk(
                            text: $part['text'],
                            index: 0,
                            finishReason: $finishReason,
                        );
                    } elseif (isset($part['functionCall'])) {
                        $fc = $part['functionCall'];
                        yield new ToolCallChunk(
                            toolCallId: 'call_' . bin2hex(random_bytes(12)),
                            toolName: $fc['name'] ?? null,
                            argumentsDelta: json_encode($fc['args'] ?? new \stdClass(), JSON_THROW_ON_ERROR),
                            toolCallIndex: 0,
                            index: 0,
                            finishReason: $finishReason,
                        );
                    }
                }

                // Usage metadata (typically in the last chunk)
                if (isset($decoded['usageMetadata'])) {
                    $um = $decoded['usageMetadata'];
                    yield new UsageChunk(
                        usage: new Usage(
                            textInputTokens: $um['promptTokenCount'] ?? 0,
                            textOutputTokens: $um['candidatesTokenCount'] ?? 0,
                        ),
                        index: 0,
                        finishReason: $finishReason,
                    );
                }
            }
        }
    }
}
