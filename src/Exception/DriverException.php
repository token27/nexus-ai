<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * Error communicating with an AI provider.
 *
 * Contains provider-specific error details: HTTP status code, error type,
 * and raw response body for debugging.
 *
 * Includes a factory method fromResponse() that parses the JSON error body
 * to extract the error type automatically.
 *
 * @see \Token27\NexusAI\Exception\RateLimitException
 * @see \Token27\NexusAI\Exception\RequestTooLargeException
 * @see \Token27\NexusAI\Exception\ProviderOverloadedException
 */
class DriverException extends NexusException
{
    /**
     * @param string $message Human-readable error message.
     * @param string $provider Name of the provider that failed.
     * @param int|null $statusCode HTTP status code from the provider.
     * @param string|null $errorType Provider error type (e.g., 'invalid_api_key', 'context_length_exceeded').
     * @param string|null $responseBody Raw response body for debugging.
     * @param \Throwable|null $previous Previous exception if wrapping.
     */
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $statusCode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /**
     * Creates a DriverException by parsing the provider's error response body.
     *
     * Attempts to extract the error type and message from common JSON formats:
     * - OpenAI:    {"error": {"message": "...", "type": "..."}}
     * - Anthropic: {"error": {"message": "...", "type": "..."}}
     * - Generic:   {"message": "...", "error": "..."}
     *
     * @param string $provider Provider name.
     * @param int $status HTTP status code.
     * @param string $body Raw response body (JSON).
     */
    public static function fromResponse(string $provider, int $status, string $body): self
    {
        $errorType = null;
        $message = "Request to {$provider} failed with status {$status}";

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            // OpenAI / Anthropic format: {"error": {"message": "...", "type": "..."}}
            if (isset($decoded['error']) && is_array($decoded['error'])) {
                $errorType = $decoded['error']['type'] ?? null;
                $message = $decoded['error']['message'] ?? $message;
            }
            // Generic format: {"message": "...", "error": "..."}
            elseif (isset($decoded['message'])) {
                $message = $decoded['message'];
                $errorType = $decoded['error'] ?? null;
                if (is_array($errorType)) {
                    $errorType = null;
                }
            }
        }

        return new self(
            message: $message,
            provider: $provider,
            statusCode: $status,
            errorType: is_string($errorType) ? $errorType : null,
            responseBody: $body,
        );
    }
}
