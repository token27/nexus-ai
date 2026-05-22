<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Pipeline\Context;

/**
 * Defines a unit of logic that intercepts the request→response flow.
 *
 * Each middleware can execute logic before, after, or instead of passing
 * to the next handler in the pipeline. Follows the PSR-15 single-method
 * pattern rather than Neuron AI's split before/after approach.
 *
 * Key capabilities:
 * - Before logic: execute code before calling $next
 * - After logic: execute code after calling $next
 * - Short-circuit: return without calling $next (e.g., cache hit)
 * - Error handling: wrap $next in try/catch
 *
 * @see \Token27\NexusAI\Pipeline\Pipeline
 * @see \Token27\NexusAI\Pipeline\Context
 */
interface MiddlewareInterface
{
    /**
     * Processes the request context through this middleware.
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context (may be a new instance if modified).
     */
    public function process(Context $context, callable $next): Context;
}
