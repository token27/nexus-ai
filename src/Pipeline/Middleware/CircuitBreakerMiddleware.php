<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Exception\DriverException;
use Token27\NexusAI\Pipeline\Context;

/**
 * Implements the Circuit Breaker pattern for provider reliability.
 *
 * Tracks consecutive failures per provider and transitions through three states:
 *
 * - **Closed** (normal): Requests pass through. Failures increment the counter.
 *   When failures >= threshold, transitions to Open.
 *
 * - **Open** (tripped): Requests fail immediately with DriverException,
 *   without making any HTTP call. After the cooldown period expires,
 *   transitions to HalfOpen.
 *
 * - **HalfOpen** (probing): Allows ONE test request through.
 *   If it succeeds → transitions to Closed (reset counter).
 *   If it fails → transitions back to Open (reset cooldown timer).
 *
 * ```
 * [Closed] --failures >= threshold--> [Open] --cooldown expired--> [HalfOpen]
 *    ^                                  ^                              |
 *    |--- request succeeds ------------|--- request fails ------------|
 * ```
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 */
final class CircuitBreakerMiddleware implements MiddlewareInterface
{
    /**
     * Consecutive failure counts per provider.
     *
     * @var array<string, int>
     */
    private array $failureCounts = [];

    /**
     * Timestamp (microtime) when the circuit was opened per provider.
     *
     * @var array<string, float>
     */
    private array $openedAt = [];

    /**
     * @param int $failureThreshold Consecutive failures required to open the circuit.
     * @param int $cooldownSeconds Seconds the circuit stays open before transitioning to HalfOpen.
     */
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60,
    ) {
    }

    /**
     * Applies circuit breaker logic around the pipeline execution.
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context.
     *
     * @throws DriverException If the circuit is open and cooldown has not expired.
     */
    public function process(Context $context, callable $next): Context
    {
        $provider = $context->getRequest()->getProvider();
        $state = $this->getState($provider);

        // If circuit is OPEN and cooldown hasn't expired → fail immediately
        if ($state === 'open') {
            throw new DriverException(
                message: sprintf(
                    'Circuit breaker open for provider "%s" — %d consecutive failures. Retry after %d seconds.',
                    $provider,
                    $this->failureCounts[$provider] ?? 0,
                    $this->getRemainingCooldown($provider),
                ),
                provider: $provider,
            );
        }

        // State is CLOSED or HALF-OPEN — allow the request through
        try {
            $context = $next($context);

            // Success → reset failure counter (Closed state)
            $this->failureCounts[$provider] = 0;
            unset($this->openedAt[$provider]);

            return $context;
        } catch (\Throwable $e) {
            // Failure → increment counter
            $this->failureCounts[$provider] = ($this->failureCounts[$provider] ?? 0) + 1;

            // If threshold reached → open the circuit
            if ($this->failureCounts[$provider] >= $this->failureThreshold) {
                $this->openedAt[$provider] = microtime(true);
            }

            throw $e;
        }
    }

    /**
     * Determines the current state of the circuit for a provider.
     *
     * @param string $provider Provider identifier.
     * @return string 'closed', 'open', or 'half-open'.
     */
    private function getState(string $provider): string
    {
        $failures = $this->failureCounts[$provider] ?? 0;

        // Not enough failures → CLOSED
        if ($failures < $this->failureThreshold) {
            return 'closed';
        }

        // Circuit was opened — check cooldown
        if (isset($this->openedAt[$provider])) {
            $elapsed = microtime(true) - $this->openedAt[$provider];

            if ($elapsed < $this->cooldownSeconds) {
                return 'open';
            }

            // Cooldown expired → HALF-OPEN (allow one test request)
            return 'half-open';
        }

        return 'closed';
    }

    /**
     * Returns the remaining cooldown time in seconds for a provider.
     *
     * @param string $provider Provider identifier.
     * @return int Remaining seconds, or 0 if not in cooldown.
     */
    private function getRemainingCooldown(string $provider): int
    {
        if (!isset($this->openedAt[$provider])) {
            return 0;
        }

        $elapsed = microtime(true) - $this->openedAt[$provider];
        $remaining = $this->cooldownSeconds - (int) $elapsed;

        return max(0, $remaining);
    }
}
