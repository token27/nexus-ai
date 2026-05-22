<?php

declare(strict_types=1);

namespace Token27\NexusAI\ValueObject;

/**
 * Response metadata from the AI provider.
 *
 * Contains the response identifier, the actual model used (which may differ
 * from what was requested), and rate limiting information extracted from headers.
 *
 * Inspired by Prism's Meta VO (readonly class with id, model, rateLimits).
 *
 * @see \Token27\NexusAI\Contract\ResponseInterface
 */
readonly class Meta
{
    /**
     * @param string|null $id Unique response ID from the provider (e.g., 'chatcmpl-xxx').
     * @param string|null $model Actual model used (may differ from requested, e.g., request 'gpt-4o' → response 'gpt-4o-2024-08-06').
     * @param array<RateLimitInfo> $rateLimits Rate limiting info extracted from HTTP headers.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $model = null,
        public array $rateLimits = [],
    ) {
    }
}
