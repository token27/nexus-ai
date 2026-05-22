<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class ContextTest extends TestCase
{
    private function makeRequest(): TextRequest
    {
        return new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Hello')],
        );
    }

    public function testCreation(): void
    {
        $request = $this->makeRequest();
        $ctx = new Context($request);

        $this->assertSame($request, $ctx->getRequest());
        $this->assertNull($ctx->getResponse());
        $this->assertNull($ctx->getUsage());
        $this->assertNull($ctx->getPricingResult());
        $this->assertSame([], $ctx->getAllMeta());
    }

    public function testWithResponseReturnsNewInstance(): void
    {
        $ctx = new Context($this->makeRequest());
        $response = new TextResponse(text: 'Hi', finishReason: FinishReason::Stop);
        $ctx2 = $ctx->withResponse($response);

        $this->assertNull($ctx->getResponse());
        $this->assertSame($response, $ctx2->getResponse());
        $this->assertNotSame($ctx, $ctx2);
    }

    public function testWithUsageReturnsNewInstance(): void
    {
        $ctx = new Context($this->makeRequest());
        $usage = new Usage(100, 50);
        $ctx2 = $ctx->withUsage($usage);

        $this->assertNull($ctx->getUsage());
        $this->assertSame($usage, $ctx2->getUsage());
    }

    public function testWithPricingResultReturnsNewInstance(): void
    {
        $ctx = new Context($this->makeRequest());
        $result = PricingResult::compute(
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
            inputTokens: 1_000,
            outputTokens: 500,
        );
        $ctx2 = $ctx->withPricingResult($result);

        $this->assertNull($ctx->getPricingResult());
        $this->assertSame($result, $ctx2->getPricingResult());
    }

    public function testWithMetaReturnsNewInstance(): void
    {
        $ctx = new Context($this->makeRequest());
        $ctx2 = $ctx->withMeta('key', 'value');

        $this->assertNull($ctx->getMeta('key'));
        $this->assertSame('value', $ctx2->getMeta('key'));
    }

    public function testMetaAccumulatesAcrossChains(): void
    {
        $ctx = new Context($this->makeRequest());
        $ctx2 = $ctx->withMeta('a', 1)->withMeta('b', 2);

        $this->assertSame(1, $ctx2->getMeta('a'));
        $this->assertSame(2, $ctx2->getMeta('b'));
    }

    public function testImmutabilityChain(): void
    {
        $request = $this->makeRequest();
        $ctx = new Context($request);

        $response = new TextResponse(text: 'Reply', finishReason: FinishReason::Stop);
        $usage = new Usage(10, 5);
        $pricingResult = PricingResult::compute(
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
            inputTokens: 10,
            outputTokens: 5,
        );

        $final = $ctx
            ->withResponse($response)
            ->withUsage($usage)
            ->withPricingResult($pricingResult)
            ->withMeta('cached', true);

        // Original unchanged
        $this->assertNull($ctx->getResponse());
        $this->assertNull($ctx->getUsage());
        $this->assertNull($ctx->getPricingResult());
        $this->assertSame([], $ctx->getAllMeta());

        // Final has everything
        $this->assertSame($response, $final->getResponse());
        $this->assertSame($usage, $final->getUsage());
        $this->assertSame($pricingResult, $final->getPricingResult());
        $this->assertTrue($final->getMeta('cached'));
        $this->assertSame($request, $final->getRequest());
    }
}
