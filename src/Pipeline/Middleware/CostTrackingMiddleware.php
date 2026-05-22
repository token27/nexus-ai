<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Exception\CostLimitException;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Exception\EstimationNotAvailableException;

final class CostTrackingMiddleware implements MiddlewareInterface
{
    private float $totalSpent = 0.0;

    public function __construct(
        private readonly PricingEngineInterface $pricingEngine,
        private readonly ?float $budgetLimit = null,
    ) {
    }

    public function process(Context $context, callable $next): Context
    {
        $model = $context->getRequest()->getModel();
        $engine = $context->getMeta('_pricing_engine') instanceof PricingEngineInterface
            ? $context->getMeta('_pricing_engine')
            : $this->pricingEngine;

        if ($this->budgetLimit !== null) {
            try {
                $text = $this->extractText($context->getRequest());
                $estimated = $engine->estimate($text, $model)->totalCostUsd();

                if (($this->totalSpent + $estimated) > $this->budgetLimit) {
                    throw new CostLimitException(
                        message: sprintf(
                            'Budget limit exceeded: $%.4f spent + $%.4f estimated > $%.4f limit',
                            $this->totalSpent,
                            $estimated,
                            $this->budgetLimit,
                        ),
                        budgetLimit: $this->budgetLimit,
                        totalSpent: $this->totalSpent,
                        estimatedCost: $estimated,
                    );
                }
            } catch (EstimationNotAvailableException) {
                // No text estimator available, skip budget pre-check.
            }
        }

        $context = $next($context);

        $response = $context->getResponse();
        if ($response !== null) {
            $result = $engine->calculateFromUsage($response->getUsage(), $model);
            $this->totalSpent += $result->totalCostUsd();
            $context = $context->withPricingResult($result);
        }

        return $context;
    }

    public function getTotalSpent(): float
    {
        return $this->totalSpent;
    }

    private function extractText(RequestInterface $request): string
    {
        $text = '';
        foreach ($request->getMessages() as $message) {
            $text .= $message->getText() . ' ';
        }

        return $text;
    }
}
