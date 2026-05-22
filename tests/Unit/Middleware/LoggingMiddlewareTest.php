<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Middleware\LoggingMiddleware;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class LoggingMiddlewareTest extends TestCase
{
    private function makeContext(): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage('Test')],
        ));
    }

    public function testPassesThroughSuccessfully(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $mw = new LoggingMiddleware($logger);

        $result = $mw->process($this->makeContext(), function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));
        });

        $this->assertSame('OK', $result->getResponse()->text);
    }
}
