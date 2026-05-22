<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Exception\NexusException;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Request\TextRequest;

/**
 * Validates the request before sending it to the driver.
 *
 * Detects configuration errors early (missing model, empty messages,
 * temperature out of range, invalid maxTokens) and throws descriptive
 * exceptions before any HTTP call is made.
 *
 * This is typically the first middleware in the pipeline.
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 * @see \Token27\NexusAI\Pipeline\Pipeline
 */
final class ValidationMiddleware implements MiddlewareInterface
{
    /**
     * Validates the request and passes to the next middleware if valid.
     *
     * Validation rules:
     * - Model must not be empty.
     * - Provider must not be empty.
     * - Temperature, if set, must be between 0.0 and 2.0.
     * - MaxTokens, if set, must be greater than 0.
     * - Messages must not be empty (unless a systemPrompt is set on TextRequest).
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The processed context.
     *
     * @throws NexusException If validation fails.
     */
    public function process(Context $context, callable $next): Context
    {
        $request = $context->getRequest();

        if ($request->getModel() === '') {
            throw new NexusException('Model is required');
        }

        if ($request->getProvider() === '') {
            throw new NexusException('Provider is required');
        }

        $temperature = $request->getTemperature();
        if ($temperature !== null && ($temperature < 0.0 || $temperature > 2.0)) {
            throw new NexusException(
                sprintf('Temperature must be between 0.0 and 2.0, got %s', $temperature),
            );
        }

        $maxTokens = $request->getMaxTokens();
        if ($maxTokens !== null && $maxTokens <= 0) {
            throw new NexusException(
                sprintf('MaxTokens must be greater than 0, got %d', $maxTokens),
            );
        }

        // Messages validation: allow empty messages if TextRequest has a systemPrompt
        $hasSystemPrompt = $request instanceof TextRequest && $request->systemPrompt !== null;
        if (empty($request->getMessages()) && !$hasSystemPrompt) {
            throw new NexusException('Messages are required (or set a systemPrompt)');
        }

        return $next($context);
    }
}
