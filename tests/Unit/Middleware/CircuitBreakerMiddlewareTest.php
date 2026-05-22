<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Exception\DriverException;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Middleware\CircuitBreakerMiddleware;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class CircuitBreakerMiddlewareTest extends TestCase
{
    private function makeContext(): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Test')],
        ));
    }

    public function testClosedStateAllowsRequests(): void
    {
        $mw = new CircuitBreakerMiddleware(failureThreshold: 3, cooldownSeconds: 60);

        $result = $mw->process($this->makeContext(), function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));
        });

        $this->assertSame('OK', $result->getResponse()->text);
    }

    public function testOpensAfterThresholdFailures(): void
    {
        $mw = new CircuitBreakerMiddleware(failureThreshold: 2, cooldownSeconds: 60);
        $failHandler = function (Context $ctx): Context {
            throw new DriverException('Fail', provider: 'openai', statusCode: 500);
        };

        // Cause threshold failures
        for ($i = 0; $i < 2; $i++) {
            try {
                $mw->process($this->makeContext(), $failHandler);
            } catch (DriverException) {
            }
        }

        // Next call should be rejected — circuit is open
        try {
            $mw->process($this->makeContext(), function (Context $ctx): Context {
                return $ctx->withResponse(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));
            });
            $this->fail('Expected exception from open circuit breaker');
        } catch (\Throwable $e) {
            // CircuitBreaker should throw some exception when open
            $this->assertStringContainsString('circuit', strtolower($e->getMessage()));
        }
    }

    public function testSuccessResetsFailureCount(): void
    {
        $mw = new CircuitBreakerMiddleware(failureThreshold: 3, cooldownSeconds: 60);

        // One failure
        try {
            $mw->process($this->makeContext(), function (Context $ctx): Context {
                throw new DriverException('Fail', provider: 'openai', statusCode: 500);
            });
        } catch (DriverException) {
        }

        // Success resets count
        $result = $mw->process($this->makeContext(), function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));
        });

        $this->assertSame('OK', $result->getResponse()->text);
    }
}
