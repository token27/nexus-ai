<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * HTTP 413 — The payload is too large (context length exceeded).
 *
 * Thrown when the total token count (prompt + expected completion) exceeds
 * the model's context window limit.
 *
 * @see \Token27\NexusAI\Exception\DriverException
 */
class RequestTooLargeException extends DriverException
{
    /**
     * @param string $message Human-readable error message.
     * @param string $provider Provider name.
     * @param string|null $responseBody Raw response body.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        string $provider,
        ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            provider: $provider,
            statusCode: 413,
            errorType: 'context_length_exceeded',
            responseBody: $responseBody,
            previous: $previous,
        );
    }
}
