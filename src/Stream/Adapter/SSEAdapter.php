<?php

declare(strict_types=1);

namespace Token27\NexusAI\Stream\Adapter;

use Generator;
use Token27\NexusAI\Contract\StreamChunkInterface;

/**
 * Transforms NexusAI stream chunks into SSE (Server-Sent Events) format.
 *
 * Each chunk is serialized as a JSON payload in the SSE `data:` field.
 * The stream is terminated with `data: [DONE]`.
 *
 * Output format per chunk:
 * ```
 * data: {"type":"text_delta","text":"Hello","finish_reason":null}
 *
 * ```
 *
 * Example usage in a PHP controller:
 * ```php
 * header('Content-Type: text/event-stream');
 * header('Cache-Control: no-cache');
 *
 * $stream = NexusAI::using('openai', 'gpt-4o')
 *     ->withPrompt('Tell me a story')
 *     ->asStream();
 *
 * $adapter = new SSEAdapter();
 * foreach ($adapter->adapt($stream) as $sseEvent) {
 *     echo $sseEvent;
 *     ob_flush();
 *     flush();
 * }
 * ```
 *
 * @see StreamAdapterInterface
 * @see \Token27\NexusAI\Response\StreamResponse
 */
final class SSEAdapter implements StreamAdapterInterface
{
    /**
     * Transforms stream chunks into SSE-formatted strings.
     *
     * For each chunk, yields a line in the format:
     *   `data: {"type":"...","text":"...","finish_reason":"..."}\n\n`
     *
     * After all chunks have been processed, yields:
     *   `data: [DONE]\n\n`
     *
     * @param Generator<int, StreamChunkInterface> $chunks Generator yielding StreamChunkInterface objects.
     * @return Generator<int, string> Generator yielding SSE-formatted strings.
     */
    public function adapt(Generator $chunks): Generator
    {
        foreach ($chunks as $chunk) {
            /** @var array<string, mixed> $payload */
            $payload = [
                'type' => $chunk->getType(),
                'text' => $chunk->getText(),
                'finish_reason' => $chunk->getFinishReason()?->value,
            ];

            yield 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "\n\n";
        }

        yield "data: [DONE]\n\n";
    }

    /**
     * Returns the MIME content type for SSE.
     *
     * @return string Always 'text/event-stream'.
     */
    public function getContentType(): string
    {
        return 'text/event-stream';
    }
}
