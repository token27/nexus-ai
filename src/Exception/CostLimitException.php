<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * INNOVATION: Accumulated spending exceeded the configured budget.
 *
 * Thrown by CostTrackingMiddleware when the total cost of all requests
 * in a session would exceed the budget limit. This prevents runaway costs
 * from bugs in tool-calling loops.
 *
 * None of the benchmark libraries (Prism, Neuron AI, LLPhant) have this.
 *
 * @see \Token27\NexusAI\Pipeline\Middleware\CostTrackingMiddleware
 */
class CostLimitException extends NexusException
{
    /**
     * @param string $message Human-readable error message.
     * @param float $budgetLimit Maximum budget configured.
     * @param float $totalSpent Total amount spent so far.
     * @param float $estimatedCost Estimated cost of the blocked request.
     */
    public function __construct(
        string $message,
        public readonly float $budgetLimit,
        public readonly float $totalSpent,
        public readonly float $estimatedCost,
    ) {
        parent::__construct($message);
    }
}
