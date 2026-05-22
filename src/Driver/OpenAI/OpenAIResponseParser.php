<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\OpenAI;

use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\Embedding;
use Token27\NexusAI\ValueObject\Meta;
use Token27\NexusAI\ValueObject\RateLimitInfo;
use Token27\NexusAI\ValueObject\ToolCall;

/**
 * Transforms OpenAI API JSON responses into NexusAI Value Objects.
 *
 * Stateless class with static methods. Handles the parsing of:
 * - Chat completion responses → TextResponse
 * - Embedding responses → EmbeddingResponse
 * - Finish reason mapping (OpenAI strings → FinishReason enum)
 * - Tool call extraction from assistant messages
 *
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIDriver
 * @see \Token27\NexusAI\Response\TextResponse
 */
final class OpenAIResponseParser
{
    /**
     * Parses an OpenAI chat completion response into a TextResponse.
     *
     * Extracts text content, finish reason, tool calls, usage, and metadata
     * from the raw JSON structure:
     * {id, model, choices: [{index, message: {role, content, tool_calls}, finish_reason}], usage: {prompt_tokens, completion_tokens}}
     *
     * @param array<string, mixed> $data Decoded JSON response from OpenAI.
     * @param array<RateLimitInfo> $rateLimits Rate limit information from headers.
     * @return TextResponse The parsed text response.
     */
    public static function parseTextResponse(array $data, array $rateLimits = []): TextResponse
    {
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        // Extract text content
        $text = $message['content'] ?? '';

        // Map finish reason
        $finishReason = self::mapFinishReason($choice['finish_reason'] ?? null);

        // Extract tool calls (if any)
        $toolCalls = self::parseToolCalls($message['tool_calls'] ?? []);

        $usage = self::parseOpenAIUsage($data['usage'] ?? []);

        // Build Meta VO
        $meta = new Meta(
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            rateLimits: $rateLimits,
        );

        return new TextResponse(
            text: $text,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: $usage,
            meta: $meta,
            steps: [],
            messages: [],
            raw: $data,
        );
    }

    /**
     * Parses an OpenAI embeddings response into an EmbeddingResponse.
     *
     * Extracts embedding vectors from the response structure:
     * {data: [{index, embedding: [float, ...]}, ...], usage: {prompt_tokens, total_tokens}}
     *
     * @param array<string, mixed> $data Decoded JSON response from OpenAI.
     * @param array<RateLimitInfo> $rateLimits Rate limit information from headers.
     * @return EmbeddingResponse The parsed embedding response.
     */
    public static function parseEmbeddingResponse(array $data, array $rateLimits = []): EmbeddingResponse
    {
        $embeddings = [];

        foreach ($data['data'] ?? [] as $item) {
            $embeddings[] = new Embedding(
                values: $item['embedding'] ?? [],
                index: $item['index'] ?? 0,
            );
        }

        $usage = self::parseOpenAIUsage($data['usage'] ?? []);

        $meta = new Meta(
            id: $data['id'] ?? null,
            model: $data['model'] ?? null,
            rateLimits: $rateLimits,
        );

        return new EmbeddingResponse(
            embeddings: $embeddings,
            usage: $usage,
            meta: $meta,
            finishReason: FinishReason::Stop,
            raw: $data,
        );
    }

    /**
     * Maps OpenAI finish_reason strings to the NexusAI FinishReason enum.
     *
     * Mapping:
     * - 'stop'           → FinishReason::Stop
     * - 'length'         → FinishReason::Length
     * - 'content_filter' → FinishReason::ContentFilter
     * - 'tool_calls'     → FinishReason::ToolCalls
     * - null             → FinishReason::Unknown
     * - other            → FinishReason::Other
     *
     * @param string|null $reason The OpenAI finish_reason value.
     * @return FinishReason The mapped NexusAI enum value.
     */
    public static function mapFinishReason(?string $reason): FinishReason
    {
        return match ($reason) {
            'stop' => FinishReason::Stop,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            'tool_calls' => FinishReason::ToolCalls,
            null => FinishReason::Unknown,
            default => FinishReason::Other,
        };
    }

    /**
     * Parses tool call objects from the OpenAI response into ToolCall VOs.
     *
     * Each OpenAI tool call has the structure:
     * {id: 'call_xxx', type: 'function', function: {name: '...', arguments: '{...}'}}
     *
     * @param array<int, array<string, mixed>> $toolCalls Raw tool call data from OpenAI.
     * @return array<ToolCall> Parsed ToolCall value objects.
     */
    public static function parseToolCalls(array $toolCalls): array
    {
        $parsed = [];

        foreach ($toolCalls as $tc) {
            $arguments = [];
            $rawArgs = $tc['function']['arguments'] ?? '{}';

            if (is_string($rawArgs) && $rawArgs !== '') {
                $decoded = json_decode($rawArgs, true);
                if (is_array($decoded)) {
                    $arguments = $decoded;
                }
            }

            $parsed[] = new ToolCall(
                id: $tc['id'] ?? '',
                name: $tc['function']['name'] ?? '',
                arguments: $arguments,
            );
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $usageData
     */
    private static function parseOpenAIUsage(array $usageData): Usage
    {
        $usage = Usage::fromOpenAI($usageData);

        if ($usage->totalTokens() > 0 || $usageData === []) {
            return $usage;
        }

        $cachedTokens = $usageData['prompt_tokens_details']['cached_tokens'] ?? null;

        return new Usage(
            textInputTokens: (int) ($usageData['prompt_tokens'] ?? 0),
            textOutputTokens: (int) ($usageData['completion_tokens'] ?? 0),
            cacheReadTokens: is_numeric($cachedTokens) ? (int) $cachedTokens : null,
        );
    }
}
