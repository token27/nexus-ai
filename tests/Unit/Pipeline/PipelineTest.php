<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Pipeline;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class PipelineTest extends TestCase
{
    private function makeContext(): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Hello')],
        ));
    }

    public function testSendWithNoMiddleware(): void
    {
        $pipeline = new Pipeline();
        $ctx = $this->makeContext();

        $handler = function (Context $c): Context {
            return $c->withResponse(new TextResponse(
                text: 'Direct',
                finishReason: FinishReason::Stop,
            ));
        };

        $result = $pipeline->send($ctx, $handler);
        $this->assertSame('Direct', $result->getResponse()->text);
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $order = [];

        $mw1 = new class ($order) implements MiddlewareInterface {
            public function __construct(private array &$order)
            {
            }

            public function process(Context $context, callable $next): Context
            {
                $this->order[] = 'mw1-before';
                $result = $next($context);
                $this->order[] = 'mw1-after';
                return $result;
            }
        };

        $mw2 = new class ($order) implements MiddlewareInterface {
            public function __construct(private array &$order)
            {
            }

            public function process(Context $context, callable $next): Context
            {
                $this->order[] = 'mw2-before';
                $result = $next($context);
                $this->order[] = 'mw2-after';
                return $result;
            }
        };

        $pipeline = (new Pipeline())->pipe($mw1)->pipe($mw2);

        $handler = function (Context $c) use (&$order): Context {
            $order[] = 'handler';
            return $c->withResponse(new TextResponse(
                text: 'Done',
                finishReason: FinishReason::Stop,
            ));
        };

        $pipeline->send($this->makeContext(), $handler);

        // Onion: mw1-before → mw2-before → handler → mw2-after → mw1-after
        $this->assertSame(['mw1-before', 'mw2-before', 'handler', 'mw2-after', 'mw1-after'], $order);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $cacheMiddleware = new class () implements MiddlewareInterface {
            public function process(Context $context, callable $next): Context
            {
                // Short-circuit — don't call $next
                return $context->withResponse(new TextResponse(
                    text: 'Cached!',
                    finishReason: FinishReason::Stop,
                ));
            }
        };

        $handlerCalled = false;
        $pipeline = (new Pipeline())->pipe($cacheMiddleware);

        $handler = function (Context $c) use (&$handlerCalled): Context {
            $handlerCalled = true;
            return $c->withResponse(new TextResponse(text: 'Live', finishReason: FinishReason::Stop));
        };

        $result = $pipeline->send($this->makeContext(), $handler);

        $this->assertFalse($handlerCalled);
        $this->assertSame('Cached!', $result->getResponse()->text);
    }

    public function testPipeReturnsNewInstance(): void
    {
        $pipeline = new Pipeline();
        $mw = new class () implements MiddlewareInterface {
            public function process(Context $context, callable $next): Context
            {
                return $next($context);
            }
        };

        $pipeline2 = $pipeline->pipe($mw);
        $this->assertNotSame($pipeline, $pipeline2);
    }
}
