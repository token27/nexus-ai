<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Gemini;

use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\Meta;
use Token27\NexusAI\ValueObject\RateLimitInfo;
use Token27\NexusAI\ValueObject\ToolCall;

/**
 * Transforms Gemini API JSON responses into NexusAI Value Objects.
 *
 * Key differences from OpenAI:
 * - Response in `candidates[0].content.parts[0].text`
 * - Tool calls in `candidates[0].content.parts[].functionCall`
 * - Finish reason: `STOP`, `MAX_TOKENS`, `SAFETY`, `OTHER`
 * - Usage in `usageMetadata` with `promptTokenCount` / `candidatesTokenCount`
 *
 * @see \Token27\NexusAI\Driver\Gemini\GeminiDriver
 */
final class GeminiResponseParser
{
    /**
     * Parses a Gemini generateContent response into a TextResponse.
     *
     * Expected structure:
     * ```json
     * {
     *     "candidates": [{
     *         "content": {"parts": [{"text": "Hello!"}], "role": "model"},
     *         "finishReason": "STOP"
     *     }],
     *     "usageMetadata": {
     *         "promptTokenCount": 10,
     *         "candidatesTokenCount": 5,
     *         "totalTokenCount": 15
     *     }
     * }
     * ```
     *
     * @param array<string, mixed> $data Decoded JSON response.
     * @param array<RateLimitInfo> $rateLimits Rate limit info from headers.
     * @return TextResponse The parsed response.
     */
    public static function parseTextResponse(array $data, array $rateLimits = []): TextResponse
    {
        $candidate = $data['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        // Extract text: concatenate all text parts
        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        $text = implode('', $texts);

        // Extract tool calls from functionCall parts
        $toolCalls = [];
        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $toolCalls[] = new ToolCall(
                    id: 'call_' . bin2hex(random_bytes(12)),
                    name: $fc['name'] ?? '',
                    arguments: is_array($fc['args'] ?? null) ? $fc['args'] : [],
                );
            }
        }

        // Map finishReason
        $finishReason = self::mapFinishReason($candidate['finishReason'] ?? null);

        $usage = self::parseGeminiUsage($data['usageMetadata'] ?? []);

        $meta = new Meta(
            id: null, // Gemini doesn't return a response ID in generateContent
            model: null,
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
     * Maps Gemini finishReason strings to NexusAI FinishReason enum.
     *
     * Mapping:
     * - 'STOP'       → FinishReason::Stop
     * - 'MAX_TOKENS' → FinishReason::Length
     * - 'SAFETY'     → FinishReason::ContentFilter
     * - 'OTHER'      → FinishReason::Other
     * - null         → FinishReason::Unknown
     *
     * @param string|null $reason The Gemini finishReason value.
     * @return FinishReason The mapped enum value.
     */
    public static function mapFinishReason(?string $reason): FinishReason
    {
        return match ($reason) {
            'STOP' => FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY' => FinishReason::ContentFilter,
            'RECITATION' => FinishReason::ContentFilter,
            'OTHER' => FinishReason::Other,
            null => FinishReason::Unknown,
            default => FinishReason::Other,
        };
    }

    /**
     * Parses Gemini usage metadata with modality-aware fallback logic.
     *
     * Gemini can expose usage in two shapes:
     * - Flat counters (`promptTokenCount`, `candidatesTokenCount`)
     * - Modality breakdown (`promptTokensDetails`, `candidatesTokensDetails`)
     *
     * When modality details are present, they take priority so image tokens
     * are not mixed into text token fields.
     *
     * @param array<string, mixed> $usageMeta
     */
    public static function parseGeminiUsage(array $usageMeta): Usage
    {
        $promptDetails = is_array($usageMeta['promptTokensDetails'] ?? null)
            ? $usageMeta['promptTokensDetails']
            : [];

        $candidateDetails = is_array($usageMeta['candidatesTokensDetails'] ?? null)
            ? $usageMeta['candidatesTokensDetails']
            : [];

        $textInputFromDetails = self::tokenCountForModality($promptDetails, 'TEXT');
        $imageInputFromDetails = self::tokenCountForModality($promptDetails, 'IMAGE');
        $textOutputFromDetails = self::tokenCountForModality($candidateDetails, 'TEXT');
        $imageOutputFromDetails = self::tokenCountForModality($candidateDetails, 'IMAGE');

        $textInput = $textInputFromDetails ?? (int) ($usageMeta['promptTokenCount'] ?? 0);
        $textOutput = $textOutputFromDetails ?? (int) ($usageMeta['candidatesTokenCount'] ?? 0);

        return new Usage(
            textInputTokens: $textInput,
            imageInputTokens: $imageInputFromDetails ?? 0,
            textOutputTokens: $textOutput,
            imageOutputTokens: $imageOutputFromDetails ?? 0,
        );
    }

    /**
     * Reads total token count for a given modality from Gemini details arrays.
     */
    private static function tokenCountForModality(mixed $details, string $modality): ?int
    {
        if (!is_array($details)) {
            return null;
        }

        $total = 0;
        $matched = false;

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $detailModality = strtoupper((string) ($detail['modality'] ?? ''));
            if ($detailModality !== $modality) {
                continue;
            }

            $matched = true;
            $total += (int) ($detail['tokenCount'] ?? 0);
        }

        return $matched ? $total : null;
    }
}
