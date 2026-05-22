<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Observability\Event\RequestCompleted;
use Token27\NexusAI\Observability\Event\RequestFailed;
use Token27\NexusAI\Observability\Observer\MetricsObserver;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class MetricsObserverTest extends TestCase
{
    private function makeRequest(): TextRequest
    {
        return new TextRequest(provider: 'openai', model: 'gpt-4o', messages: [new UserMessage('Hi')]);
    }

    private function makeResponse(): TextResponse
    {
        return new TextResponse(text: 'OK', finishReason: FinishReason::Stop);
    }

    public function testTracksCompletedRequests(): void
    {
        $obs = new MetricsObserver();
        $event = new RequestCompleted(
            request: $this->makeRequest(),
            response: $this->makeResponse(),
            usage: new Usage(100, 50),
            pricingResult: PricingResult::compute(
                new ModelPrice('gpt-4o', inputPerMillion: 10.0, outputPerMillion: 30.0),
                inputTokens: 1_000,
                outputTokens: 1_000,
            ),
            elapsedMs: 150.0,
        );

        $obs->onEvent('request.completed', $this, $event);
        $snap = $obs->getSnapshot();

        $this->assertSame(1, $snap['total_requests']);
        $this->assertSame(150, $snap['total_tokens']);
        $this->assertEqualsWithDelta(0.04, $snap['total_cost'], 0.001);
        $this->assertSame(0, $snap['total_errors']);
    }

    public function testTracksFailedRequests(): void
    {
        $obs = new MetricsObserver();
        $event = new RequestFailed(
            request: $this->makeRequest(),
            exception: new \RuntimeException('fail'),
            elapsedMs: 50.0,
        );

        $obs->onEvent('request.failed', $this, $event);
        $snap = $obs->getSnapshot();

        $this->assertSame(1, $snap['total_requests']);
        $this->assertSame(1, $snap['total_errors']);
        $this->assertEqualsWithDelta(1.0, $snap['error_rate'], 0.001);
    }

    public function testReset(): void
    {
        $obs = new MetricsObserver();
        $event = new RequestCompleted(
            request: $this->makeRequest(),
            response: $this->makeResponse(),
            usage: new Usage(10, 5),
            pricingResult: null,
            elapsedMs: 100.0,
        );
        $obs->onEvent('request.completed', $this, $event);
        $obs->reset();

        $snap = $obs->getSnapshot();
        $this->assertSame(0, $snap['total_requests']);
        $this->assertSame(0, $snap['total_tokens']);
    }

    public function testIgnoresNonObjectData(): void
    {
        $obs = new MetricsObserver();
        $obs->onEvent('unknown', $this, 'string data');

        $this->assertSame(0, $obs->getSnapshot()['total_requests']);
    }
}
