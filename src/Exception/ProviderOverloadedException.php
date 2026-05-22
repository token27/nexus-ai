<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * HTTP 503/529 — The provider is overloaded.
 *
 * Contains retry timing information for backoff strategies.
 *
 * @see \Token27\NexusAI\Pipeline\Middleware\RetryMiddleware
 */
class ProviderOverloadedException extends DriverException
{
    /**
     * @param string $message Human-readable error message.
     * @param string $provider Provider name.
     * @param int|null $retryAfter Suggested seconds to wait before retrying.
     * @param string|null $responseBody Raw response body.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        string $provider,
        public readonly ?int $retryAfter = null,
        ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            provider: $provider,
            statusCode: 503,
            errorType: 'overloaded',
            responseBody: $responseBody,
            previous: $previous,
        );
    }
}
