<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Psr\Log\LoggerInterface;
use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Pipeline\Context;

/**
 * Logs request lifecycle events using a PSR-3 Logger.
 *
 * Records a log entry before the request is sent (provider + model),
 * a log entry after the response is received (elapsed time, tokens, cost,
 * finish reason), and an error entry if the request fails.
 *
 * The $logPayload option is disabled by default for security reasons —
 * payloads may contain API keys or sensitive user data.
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param string $level Log level for normal messages (default: 'info').
     * @param bool $logPayload Whether to log the full request payload (default: false for security).
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = 'info',
        private readonly bool $logPayload = false,
    ) {
    }

    /**
     * Logs the request start, completion (or failure), and passes to the next middleware.
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context.
     */
    public function process(Context $context, callable $next): Context
    {
        $request = $context->getRequest();

        $startContext = [
            'provider' => $request->getProvider(),
            'model' => $request->getModel(),
        ];

        if ($this->logPayload) {
            $startContext['messages_count'] = count($request->getMessages());
            $startContext['options'] = $request->getOptions();
        }

        $this->logger->log($this->level, 'NexusAI request starting', $startContext);

        try {
            $context = $next($context);
        } catch (\Throwable $e) {
            $this->logger->error('NexusAI request failed', [
                'provider' => $request->getProvider(),
                'model' => $request->getModel(),
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
                'elapsed_ms' => $context->getElapsedMs(),
            ]);

            throw $e;
        }

        $completedContext = [
            'provider' => $request->getProvider(),
            'model' => $request->getModel(),
            'elapsed_ms' => $context->getElapsedMs(),
        ];

        $usage = $context->getUsage();
        if ($usage !== null) {
            $completedContext['tokens'] = $usage->totalTokens();
        }

        $pricingResult = $context->getPricingResult();
        if ($pricingResult !== null) {
            $completedContext['cost'] = $pricingResult->totalCostUsd();
        }

        $response = $context->getResponse();
        if ($response !== null) {
            $completedContext['finish_reason'] = $response->getFinishReason()->value;
        }

        $this->logger->log($this->level, 'NexusAI request completed', $completedContext);

        return $context;
    }
}
