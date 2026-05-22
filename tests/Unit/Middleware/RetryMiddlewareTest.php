<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Exception\RateLimitException;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Middleware\RetryMiddleware;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class RetryMiddlewareTest extends TestCase
{
    private function makeContext(): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Test')],
        ));
    }

    public function testNoRetryOnSuccess(): void
    {
        $mw = new RetryMiddleware(maxRetries: 3);
        $callCount = 0;

        $result = $mw->process($this->makeContext(), function (Context $ctx) use (&$callCount): Context {
            $callCount++;
            return $ctx->withResponse(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));
        });

        $this->assertSame(1, $callCount);
        $this->assertSame('OK', $result->getResponse()->text);
    }

    public function testRetriesOnRetryableException(): void
    {
        $mw = new RetryMiddleware(maxRetries: 3, baseDelayMs: 0);
        $callCount = 0;

        $result = $mw->process($this->makeContext(), function (Context $ctx) use (&$callCount): Context {
            $callCount++;
            if ($callCount < 3) {
                throw new RateLimitException('Rate limited', provider: 'openai');
            }
            return $ctx->withResponse(new TextResponse(text: 'Recovered', finishReason: FinishReason::Stop));
        });

        $this->assertSame(3, $callCount);
        $this->assertSame('Recovered', $result->getResponse()->text);
    }

    public function testThrowsAfterMaxRetries(): void
    {
        $mw = new RetryMiddleware(maxRetries: 2, baseDelayMs: 0);

        $this->expectException(RateLimitException::class);

        $mw->process($this->makeContext(), function (Context $ctx): Context {
            throw new RateLimitException('Always fails', provider: 'openai');
        });
    }

    public function testDoesNotRetryNonRetryableException(): void
    {
        $mw = new RetryMiddleware(maxRetries: 3, baseDelayMs: 0);
        $callCount = 0;

        try {
            $mw->process($this->makeContext(), function (Context $ctx) use (&$callCount): Context {
                $callCount++;
                throw new \InvalidArgumentException('Not retryable');
            });
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertSame(1, $callCount);
    }
}
