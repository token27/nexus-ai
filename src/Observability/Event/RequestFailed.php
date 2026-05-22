<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Event;

use Token27\NexusAI\Contract\RequestInterface;

/**
 * Event emitted when the pipeline fails with an uncaught exception.
 *
 * Contains the original request, the exception, and elapsed time
 * for error tracking and alerting.
 *
 * @see \Token27\NexusAI\Observability\EventBus
 */
final readonly class RequestFailed
{
    /**
     * @param RequestInterface $request The original request that failed.
     * @param \Throwable $exception The exception that caused the failure.
     * @param float $elapsedMs Elapsed time in milliseconds before the failure.
     */
    public function __construct(
        public RequestInterface $request,
        public \Throwable $exception,
        public float $elapsedMs,
    ) {
    }
}
