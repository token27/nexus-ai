<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Event;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\ValueObject\Usage;

/**
 * Event emitted at the end of a successful pipeline execution.
 *
 * Contains the full request/response pair, usage statistics, calculated
 * cost, and elapsed time.
 *
 * @see \Token27\NexusAI\Observability\EventBus
 */
final readonly class RequestCompleted
{
    /**
     * @param RequestInterface $request The original request.
     * @param ResponseInterface $response The driver response.
     * @param Usage|null $usage Token usage statistics, if available.
     * @param PricingResultInterface|null $pricingResult Calculated financial cost, if available.
     * @param float $elapsedMs Elapsed time in milliseconds.
     */
    public function __construct(
        public RequestInterface $request,
        public ResponseInterface $response,
        public ?Usage $usage,
        public ?PricingResultInterface $pricingResult,
        public float $elapsedMs,
    ) {
    }
}
