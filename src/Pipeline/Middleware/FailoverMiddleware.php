<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Driver\DriverRegistry;
use Token27\NexusAI\Exception\DriverException;
use Token27\NexusAI\Exception\ProviderOverloadedException;
use Token27\NexusAI\Exception\RateLimitException;
use Token27\NexusAI\Exception\RequestTooLargeException;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Request\TextRequest;

/**
 * Automatic failover middleware for provider resilience.
 *
 * When the primary provider fails with a retryable exception, this middleware
 * automatically switches to the next provider in the fallback chain. Each
 * fallback is tried in order until one succeeds or all are exhausted.
 *
 * INNOVATION: None of the three benchmark libraries (Prism, Neuron AI, LLPhant)
 * have automatic cross-provider failover. This is a NexusAI-exclusive feature.
 *
 * Only retryable exceptions trigger failover:
 * - RateLimitException (HTTP 429)
 * - ProviderOverloadedException (HTTP 503/529)
 * - RequestTooLargeException (HTTP 413)
 * - Generic DriverException (server errors, timeouts)
 *
 * Authentication errors (HTTP 401/403) are NOT retryable — they indicate
 * configuration problems that won't be solved by switching providers.
 *
 * Usage:
 * ```php
 * new FailoverMiddleware(
 *     fallbacks: [
 *         ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-20250514'],
 *         ['provider' => 'gemini', 'model' => 'gemini-1.5-pro'],
 *     ],
 *     registry: $registry
 * );
 * ```
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 * @see \Token27\NexusAI\Driver\DriverRegistry
 */
final class FailoverMiddleware implements MiddlewareInterface
{
    /**
     * @param array<array{provider: string, model: string}> $fallbacks Ordered list of fallback providers.
     * @param DriverRegistry $registry Registry to resolve fallback driver instances.
     */
    public function __construct(
        private readonly array $fallbacks,
        private readonly DriverRegistry $registry,
    ) {
    }

    /**
     * Processes the request with automatic failover on retryable exceptions.
     *
     * Flow:
     * 1. Try the original request via $next (normal pipeline flow)
     * 2. On retryable exception → iterate fallbacks
     * 3. For each fallback: resolve driver, build new request, call driver directly
     * 4. If fallback succeeds → return response with metadata noting the failover
     * 5. If all fallbacks fail → re-throw the last exception
     *
     * Fallback calls bypass the pipeline to avoid infinite recursion —
     * the driver is called directly via its text() method.
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context (possibly from a fallback provider).
     *
     * @throws \Throwable Re-throws if the exception is not retryable or all fallbacks fail.
     */
    public function process(Context $context, callable $next): Context
    {
        try {
            return $next($context);
        } catch (\Throwable $e) {
            if (!$this->isRetryable($e)) {
                throw $e;
            }

            return $this->attemptFallbacks($context, $e);
        }
    }

    /**
     * Iterates through fallback providers attempting to fulfill the request.
     *
     * @param Context $context The original pipeline context.
     * @param \Throwable $originalError The exception from the primary provider.
     * @return Context The context with a response from a fallback provider.
     *
     * @throws \Throwable Re-throws the last exception if all fallbacks fail.
     */
    private function attemptFallbacks(Context $context, \Throwable $originalError): Context
    {
        $lastException = $originalError;

        foreach ($this->fallbacks as $fallback) {
            try {
                $driver = $this->registry->resolve($fallback['provider']);
                $response = $this->callFallbackDriver($driver, $context, $fallback);

                // Return context with response and metadata about the failover
                return $context
                    ->withResponse($response)
                    ->withMeta('failover', true)
                    ->withMeta('failover_provider', $fallback['provider'])
                    ->withMeta('failover_model', $fallback['model'])
                    ->withMeta('failover_original_error', $originalError->getMessage());
            } catch (\Throwable $e) {
                $lastException = $e;
                // Continue to next fallback
            }
        }

        // All fallbacks failed — re-throw the last exception
        throw $lastException;
    }

    /**
     * Calls a fallback driver directly, bypassing the pipeline.
     *
     * Creates a new TextRequest with the fallback provider and model,
     * preserving all other parameters from the original request.
     *
     * @param DriverInterface $driver The resolved fallback driver.
     * @param Context $context The original pipeline context.
     * @param array{provider: string, model: string} $fallback The fallback configuration.
     * @return \Token27\NexusAI\Contract\ResponseInterface The response from the fallback driver.
     */
    private function callFallbackDriver(
        DriverInterface $driver,
        Context $context,
        array $fallback,
    ): \Token27\NexusAI\Contract\ResponseInterface {
        $originalRequest = $context->getRequest();

        // Only TextRequest is supported for failover currently
        if ($originalRequest instanceof TextRequest) {
            $fallbackRequest = new TextRequest(
                provider: $fallback['provider'],
                model: $fallback['model'],
                messages: $originalRequest->messages,
                systemPrompt: $originalRequest->systemPrompt,
                maxTokens: $originalRequest->maxTokens,
                temperature: $originalRequest->temperature,
                topP: $originalRequest->topP,
                tools: $originalRequest->tools,
                toolChoice: $originalRequest->toolChoice,
                maxSteps: $originalRequest->maxSteps,
                options: [], // Don't pass provider-specific options to a different provider
            );

            return $driver->text($fallbackRequest);
        }

        // For non-text requests, attempt text() with minimal params
        $fallbackRequest = new TextRequest(
            provider: $fallback['provider'],
            model: $fallback['model'],
            messages: $originalRequest->getMessages(),
            maxTokens: $originalRequest->getMaxTokens(),
            temperature: $originalRequest->getTemperature(),
        );

        return $driver->text($fallbackRequest);
    }

    /**
     * Determines whether an exception should trigger a failover attempt.
     *
     * Retryable exceptions indicate transient provider issues. Authentication
     * errors (HTTP 401/403) are NOT retryable — they indicate configuration
     * problems that won't be fixed by switching providers.
     *
     * @param \Throwable $e The exception to evaluate.
     * @return bool True if the exception should trigger failover.
     */
    private function isRetryable(\Throwable $e): bool
    {
        // Rate limiting and overload — definitely retry with another provider
        if ($e instanceof RateLimitException) {
            return true;
        }

        if ($e instanceof ProviderOverloadedException) {
            return true;
        }

        if ($e instanceof RequestTooLargeException) {
            return true;
        }

        // Generic driver errors — check for auth failures
        if ($e instanceof DriverException) {
            // Authentication errors are NOT retryable
            $status = $e->statusCode;
            if ($status === 401 || $status === 403) {
                return false;
            }

            // Server errors, timeouts, etc. → retryable
            return true;
        }

        // Non-driver exceptions (e.g., JSON errors, logic errors) are not retryable
        return false;
    }
}
