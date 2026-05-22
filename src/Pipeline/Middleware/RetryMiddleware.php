<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Exception\ProviderOverloadedException;
use Token27\NexusAI\Exception\RateLimitException;
use Token27\NexusAI\Pipeline\Context;

/**
 * Retries failed requests with exponential backoff and optional jitter.
 *
 * Captures transient exceptions (RateLimitException, ProviderOverloadedException
 * by default) and re-executes the remainder of the pipeline. If the exception
 * carries a retryAfter value (e.g., from the Retry-After header), that value
 * takes precedence over the calculated delay.
 *
 * Unlike Prism (Laravel Http retry) and Neuron AI (no retry), this middleware
 * applies to ALL drivers automatically as a pipeline concern.
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 * @see \Token27\NexusAI\Exception\RateLimitException
 * @see \Token27\NexusAI\Exception\ProviderOverloadedException
 */
final class RetryMiddleware implements MiddlewareInterface
{
    /** @var array<class-string<\Throwable>> Exception classes that merit retry. */
    private readonly array $retryableExceptions;

    /**
     * @param int $maxRetries Maximum number of retry attempts.
     * @param int $baseDelayMs Base delay in milliseconds for exponential backoff.
     * @param float $multiplier Exponential multiplier applied to the base delay per attempt.
     * @param array<class-string<\Throwable>> $retryableExceptions Exception classes that should trigger a retry.
     * @param bool $jitter Whether to add random jitter to prevent thundering herd.
     */
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 1000,
        private readonly float $multiplier = 2.0,
        array $retryableExceptions = [],
        private readonly bool $jitter = true,
    ) {
        $this->retryableExceptions = $retryableExceptions !== []
            ? $retryableExceptions
            : [RateLimitException::class, ProviderOverloadedException::class];
    }

    /**
     * Executes the pipeline with retry logic on transient failures.
     *
     * Retry loop:
     * 1. Call $next($context)
     * 2. On exception: check if retryable and under max attempts
     * 3. Calculate delay: baseDelayMs * (multiplier ^ attempt) + optional jitter
     * 4. If exception has retryAfter, use that instead
     * 5. Sleep and retry
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context.
     *
     * @throws \Throwable Re-throws the last exception if all retries are exhausted or exception is not retryable.
     */
    public function process(Context $context, callable $next): Context
    {
        $attempt = 0;

        while (true) {
            try {
                return $next($context);
            } catch (\Throwable $e) {
                if (!$this->isRetryable($e)) {
                    throw $e;
                }

                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                $delayMs = $this->calculateDelay($attempt, $e);
                usleep((int) ($delayMs * 1000)); // ms → μs

                $attempt++;
            }
        }
    }

    /**
     * Checks whether the given exception is in the retryable list.
     *
     * @param \Throwable $e The exception to check.
     * @return bool True if the exception should trigger a retry.
     */
    private function isRetryable(\Throwable $e): bool
    {
        foreach ($this->retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculates the delay for the given attempt, with optional jitter.
     *
     * If the exception carries a retryAfter value, that takes precedence.
     *
     * @param int $attempt Current attempt number (0-based).
     * @param \Throwable $e The exception (may carry retryAfter).
     * @return float Delay in milliseconds.
     */
    private function calculateDelay(int $attempt, \Throwable $e): float
    {
        // Respect retryAfter from the exception (in seconds)
        if ($e instanceof RateLimitException && $e->retryAfter !== null) {
            return (float) ($e->retryAfter * 1000);
        }

        if ($e instanceof ProviderOverloadedException && $e->retryAfter !== null) {
            return (float) ($e->retryAfter * 1000);
        }

        // Exponential backoff: baseDelayMs * (multiplier ^ attempt)
        $delay = $this->baseDelayMs * ($this->multiplier ** $attempt);

        // Add jitter: random 0-10% of delay
        if ($this->jitter) {
            $delay += $delay * (mt_rand(0, 100) / 1000); // 0% to 10%
        }

        return $delay;
    }
}
