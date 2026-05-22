<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Exception\CostLimitException;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Middleware\CostTrackingMiddleware;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class CostTrackingMiddlewareTest extends TestCase
{
    private function makeEngine(): PricingEngineInterface
    {
        return PricingEngine::withTable(new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
        ]));
    }

    private function makeContext(): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Test')],
        ));
    }

    private function successHandler(): callable
    {
        return function (Context $ctx): Context {
            return $ctx
                ->withResponse(new TextResponse(
                    text: 'OK',
                    finishReason: FinishReason::Stop,
                    usage: new Usage(textInputTokens: 1000, textOutputTokens: 500),
                ));
        };
    }

    public function testAllowsRequestWithinBudget(): void
    {
        $mw = new CostTrackingMiddleware($this->makeEngine(), budgetLimit: 10.0);

        $result = $mw->process($this->makeContext(), $this->successHandler());
        $this->assertNotNull($result->getResponse());
        $this->assertNotNull($result->getPricingResult());
    }

    public function testTracksCostAcrossRequests(): void
    {
        $mw = new CostTrackingMiddleware($this->makeEngine(), budgetLimit: 10.0);

        $mw->process($this->makeContext(), $this->successHandler());
        $mw->process($this->makeContext(), $this->successHandler());

        $this->assertGreaterThan(0.0, $mw->getTotalSpent());
    }

    public function testThrowsWhenBudgetExceeded(): void
    {
        $engine = $this->createMock(PricingEngineInterface::class);
        $modelPrice = new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00);
        $estimate = PricingResult::compute($modelPrice, inputTokens: 1_000_000, outputTokens: 0);
        $actual = PricingResult::compute($modelPrice, inputTokens: 1_000, outputTokens: 500);

        $engine->method('estimate')->willReturn($estimate);
        $engine->method('calculateFromUsage')->willReturn($actual);

        $mw = new CostTrackingMiddleware($engine, budgetLimit: 0.1);

        $this->expectException(CostLimitException::class);
        $mw->process($this->makeContext(), $this->successHandler());
    }
}
