<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Ollama;

use Token27\NexusAI\Driver\OpenAI\OpenAIPayloadBuilder;
use Token27\NexusAI\Request\TextRequest;

/**
 * Transforms NexusAI domain objects into Ollama-compatible API payload format.
 *
 * Extends OpenAIPayloadBuilder behavior with Ollama-specific overrides:
 * - Does not send `max_completion_tokens` (uses `num_predict` in options instead)
 * - Accepts Ollama-specific passthrough options: `num_ctx`, `num_predict`, `repeat_penalty`
 *
 * Ollama uses the OpenAI-compatible API at `/v1/chat/completions`,
 * so most of the payload format is identical to OpenAI.
 *
 * @see \Token27\NexusAI\Driver\Ollama\OllamaDriver
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIPayloadBuilder
 */
final class OllamaPayloadBuilder
{
    /**
     * Builds the complete API payload for an Ollama chat completion request.
     *
     * Delegates to OpenAIPayloadBuilder but adjusts Ollama-specific fields:
     * - Replaces `max_completion_tokens` with `options.num_predict`
     * - Passes through Ollama-specific options
     *
     * @param TextRequest $request The text request.
     * @param bool $stream Whether to enable streaming.
     * @return array<string, mixed> The complete payload.
     */
    public static function buildTextPayload(TextRequest $request, bool $stream = false): array
    {
        $payload = OpenAIPayloadBuilder::buildTextPayload($request, $stream);

        // Ollama doesn't support max_completion_tokens; use num_predict in options
        if (isset($payload['max_completion_tokens'])) {
            $numPredict = $payload['max_completion_tokens'];
            unset($payload['max_completion_tokens']);

            // Add to Ollama options if not already set via passthrough
            if (!isset($payload['options']['num_predict'])) {
                $payload['options'] = array_merge(
                    $payload['options'] ?? [],
                    ['num_predict' => $numPredict],
                );
            }
        }

        // Remove stream_options (Ollama doesn't support OpenAI's include_usage)
        unset($payload['stream_options']);

        return $payload;
    }
}
