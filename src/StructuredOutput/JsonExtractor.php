<?php

declare(strict_types=1);

namespace Token27\NexusAI\StructuredOutput;

use function json_decode;

use const JSON_THROW_ON_ERROR;

use JsonException;

use function preg_match;
use function strpos;
use function strrpos;
use function substr;

use Token27\NexusAI\Exception\StructuredOutputException;

use function trim;

/**
 * Extracts valid JSON from raw LLM response text.
 *
 * LLMs often return JSON wrapped in markdown code blocks or surrounded by
 * explanatory text. This extractor uses a priority-based strategy:
 *
 * 1. Try direct json_decode (text is already pure JSON)
 * 2. Search for ```json...``` markdown code blocks
 * 3. Find the first `{` and last `}` (or `[` and `]`) as boundaries
 * 4. Throw StructuredOutputException if nothing works
 *
 * @see \Token27\NexusAI\Exception\StructuredOutputException
 */
final class JsonExtractor
{
    /**
     * Extract valid JSON from a raw text response.
     *
     * @param string $text The raw text from the LLM response.
     * @return string A clean, valid JSON string.
     * @throws StructuredOutputException If no valid JSON could be extracted.
     */
    public static function extract(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new StructuredOutputException(
                'Cannot extract JSON from empty response',
                responseText: $text,
                schema: [],
            );
        }

        // Attempt 1: Direct JSON parse
        if (self::isValidJson($trimmed)) {
            return $trimmed;
        }

        // Attempt 2: Extract from markdown ```json...``` block
        $fromMarkdown = self::extractFromMarkdown($trimmed);
        if ($fromMarkdown !== null && self::isValidJson($fromMarkdown)) {
            return $fromMarkdown;
        }

        // Attempt 3: Find first/last braces for objects
        $fromBraces = self::extractFromBraces($trimmed, '{', '}');
        if ($fromBraces !== null && self::isValidJson($fromBraces)) {
            return $fromBraces;
        }

        // Attempt 3b: Try square brackets for arrays
        $fromBrackets = self::extractFromBraces($trimmed, '[', ']');
        if ($fromBrackets !== null && self::isValidJson($fromBrackets)) {
            return $fromBrackets;
        }

        throw new StructuredOutputException(
            'Could not extract valid JSON from response',
            responseText: $text,
            schema: [],
        );
    }

    /**
     * @param string $text The string to validate.
     */
    private static function isValidJson(string $text): bool
    {
        try {
            json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * Extract JSON from a markdown ```json...``` code block.
     *
     * @param string $text The text to search.
     * @return string|null The extracted JSON, or null if no block found.
     */
    private static function extractFromMarkdown(string $text): ?string
    {
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\}|\[[\s\S]*?\])\s*```/', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract JSON by finding the first opening and last closing delimiter.
     *
     * @param string $text The text to search.
     * @param string $open Opening character ('{' or '[').
     * @param string $close Closing character ('}' or ']').
     * @return string|null The extracted substring, or null if not found.
     */
    private static function extractFromBraces(string $text, string $open, string $close): ?string
    {
        $firstOpen = strpos($text, $open);
        if ($firstOpen === false) {
            return null;
        }

        $lastClose = strrpos($text, $close);
        if ($lastClose === false || $lastClose < $firstOpen) {
            return null;
        }

        return substr($text, $firstOpen, $lastClose - $firstOpen + 1);
    }
}
