<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * HTTP 429 — Rate limit reached.
 *
 * Contains retry timing information and detailed rate limit data
 * for intelligent retry strategies.
 *
 * @see \Token27\NexusAI\Pipeline\Middleware\RetryMiddleware
 * @see \Token27\NexusAI\ValueObject\RateLimitInfo
 */
class RateLimitException extends DriverException
{
    /**
     * @param string $message Human-readable error message.
     * @param string $provider Provider name.
     * @param int|null $retryAfter Seconds to wait before retrying (from Retry-After header).
     * @param array<RateLimitInfo> $rateLimits Detailed rate limit information.
     * @param string|null $responseBody Raw response body.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        string $provider,
        public readonly ?int $retryAfter = null,
        public readonly array $rateLimits = [],
        ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            provider: $provider,
            statusCode: 429,
            errorType: 'rate_limit_exceeded',
            responseBody: $responseBody,
            previous: $previous,
        );
    }
}
