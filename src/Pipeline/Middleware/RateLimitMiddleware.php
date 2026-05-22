<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Exception\RateLimitException;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Pre-checks known rate limits BEFORE sending a request.
 *
 * If the last response from a provider indicated remaining = 0, this middleware
 * either waits until the reset time (waitOnLimit = true) or throws immediately
 * (waitOnLimit = false), avoiding a wasted 429 response.
 *
 * After each successful response, updates the internal rate limit state from
 * the response metadata for subsequent calls.
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 * @see \Token27\NexusAI\ValueObject\RateLimitInfo
 * @see \Token27\NexusAI\Exception\RateLimitException
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Last known rate limits per provider.
     *
     * @var array<string, array<RateLimitInfo>>
     */
    private array $lastRateLimits = [];

    /**
     * @param bool $waitOnLimit If true, waits until the rate limit resets. If false, throws immediately.
     */
    public function __construct(
        private readonly bool $waitOnLimit = true,
    ) {
    }

    /**
     * Checks rate limits before the request and updates them after.
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context.
     *
     * @throws RateLimitException If rate limit is reached and waitOnLimit is false.
     */
    public function process(Context $context, callable $next): Context
    {
        $provider = $context->getRequest()->getProvider();

        // BEFORE: Check known rate limits
        if (isset($this->lastRateLimits[$provider])) {
            foreach ($this->lastRateLimits[$provider] as $rateLimit) {
                if ($rateLimit->remaining !== null && $rateLimit->remaining === 0) {
                    if ($this->waitOnLimit && $rateLimit->resetsAt !== null) {
                        $waitSeconds = $rateLimit->resetsAt->getTimestamp() - time();
                        if ($waitSeconds > 0) {
                            usleep($waitSeconds * 1_000_000);
                        }
                    } elseif (!$this->waitOnLimit) {
                        throw new RateLimitException(
                            message: sprintf(
                                'Rate limit reached for provider "%s" (limit: %s)',
                                $provider,
                                $rateLimit->name,
                            ),
                            provider: $provider,
                            rateLimits: $this->lastRateLimits[$provider],
                        );
                    }
                }
            }
        }

        // Execute the rest of the pipeline
        $context = $next($context);

        // AFTER: Extract and store rate limits from response metadata
        $response = $context->getResponse();
        if ($response !== null) {
            $rateLimits = $response->getMeta()->rateLimits;
            if (!empty($rateLimits)) {
                $this->lastRateLimits[$provider] = $rateLimits;
            }
        }

        return $context;
    }
}
