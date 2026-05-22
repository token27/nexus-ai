<?php

declare(strict_types=1);

namespace Token27\NexusAI\ValueObject;

use DateTimeImmutable;

/**
 * Rate limiting information extracted from HTTP response headers.
 *
 * Header patterns by provider:
 * - OpenAI:    x-ratelimit-limit-requests, x-ratelimit-remaining-requests, x-ratelimit-reset-requests
 * - Anthropic: anthropic-ratelimit-requests-limit, anthropic-ratelimit-requests-remaining, anthropic-ratelimit-requests-reset
 * - Gemini:    No standard rate limit headers
 *
 * @see \Token27\NexusAI\ValueObject\Meta
 * @see \Token27\NexusAI\Exception\RateLimitException
 */
readonly class RateLimitInfo
{
    /**
     * @param string $name Type of limit (e.g., 'requests', 'tokens', 'input_tokens').
     * @param int|null $limit Total limit value (e.g., 10000).
     * @param int|null $remaining How many remain (e.g., 9500).
     * @param DateTimeImmutable|null $resetsAt When the limit resets. Parsed from the reset header.
     */
    public function __construct(
        public string $name,
        public ?int $limit = null,
        public ?int $remaining = null,
        public ?DateTimeImmutable $resetsAt = null,
    ) {
    }
}
