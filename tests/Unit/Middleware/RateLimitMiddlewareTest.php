<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Middleware\RateLimitMiddleware;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class RateLimitMiddlewareTest extends TestCase
{
    private function makeContext(): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Test')],
        ));
    }

    public function testAllowsRequestsWhenNoRateLimitKnown(): void
    {
        $mw = new RateLimitMiddleware(waitOnLimit: false);

        $result = $mw->process($this->makeContext(), function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));
        });

        $this->assertSame('OK', $result->getResponse()->text);
    }

    public function testPassesThroughNormally(): void
    {
        $mw = new RateLimitMiddleware(waitOnLimit: true);

        $result = $mw->process($this->makeContext(), function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(text: 'Fine', finishReason: FinishReason::Stop));
        });

        $this->assertSame('Fine', $result->getResponse()->text);
    }
}
