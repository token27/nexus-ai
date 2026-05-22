<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Event;

use Token27\NexusAI\Contract\RequestInterface;

/**
 * Event emitted at the start of the pipeline, before any middleware executes.
 *
 * Contains the original request and a high-resolution timestamp for latency
 * measurement.
 *
 * @see \Token27\NexusAI\Observability\EventBus
 */
final readonly class RequestStarted
{
    /**
     * @param RequestInterface $request The original request being processed.
     * @param float $timestamp High-resolution timestamp (microtime(true)).
     */
    public function __construct(
        public RequestInterface $request,
        public float $timestamp,
    ) {
    }
}
