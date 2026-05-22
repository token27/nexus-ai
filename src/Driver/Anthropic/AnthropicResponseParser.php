<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Anthropic;

use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\Meta;
use Token27\NexusAI\ValueObject\RateLimitInfo;
use Token27\NexusAI\ValueObject\ToolCall;

/**
 * Transforms Anthropic Messages API JSON responses into NexusAI Value Objects.
 *
 * Key differences from OpenAI:
 * - Content is an array of typed blocks, not a single string
 * - Tool calls are content blocks with type 'tool_use'
 * - stop_reason: end_turn→Stop, max_tokens→Length, tool_use→ToolCalls
 * - Usage includes cache tokens
 *
 * @see \Token27\NexusAI\Driver\Anthropic\AnthropicDriver
 */
final class AnthropicResponseParser
{
    /**
     * Parses an Anthropic Messages response into a TextResponse.
     *
     * @param array<string, mixed> $data Decoded JSON response.
     * @param array<RateLimitInfo> $rateLimits Rate limit info from headers.
     * @return TextResponse The parsed response.
     */
    public static function parseTextResponse(array $data, array $rateLimits = []): TextResponse
    {
        $contentBlocks = $data['content'] ?? [];

        // Extract text: concatenate all text blocks
        $texts = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }
        $text = implode('', $texts);

        // Extract tool calls from tool_use blocks
        $toolCalls = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'] ?? '',
                    name: $block['name'] ?? '',
                    arguments: is_array($block['input'] ?? null) ? $block['input'] : [],
                );
            }
        }

        $finishReason = self::mapFinishReason($data['stop_reason'] ?? null);

        $usage = Usage::fromAnthropic($data['usage'] ?? []);

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
     * Maps Anthropic stop_reason to NexusAI FinishReason.
     *
     * @param string|null $reason The Anthropic stop_reason value.
     * @return FinishReason The mapped enum value.
     */
    public static function mapFinishReason(?string $reason): FinishReason
    {
        return match ($reason) {
            'end_turn', 'stop_sequence' => FinishReason::Stop,
            'max_tokens' => FinishReason::Length,
            'tool_use' => FinishReason::ToolCalls,
            null => FinishReason::Unknown,
            default => FinishReason::Other,
        };
    }
}
