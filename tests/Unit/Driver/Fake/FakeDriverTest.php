<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Driver\Fake;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Driver\Fake\FakeDriver;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class FakeDriverTest extends TestCase
{
    private function makeRequest(string $prompt = 'Hello'): TextRequest
    {
        return new TextRequest(
            provider: 'fake',
            model: 'fake-model',
            messages: [new UserMessage($prompt)],
        );
    }

    public function testWillReturnAndText(): void
    {
        $driver = new FakeDriver();
        $driver->willReturn(new TextResponse(text: 'Hello back!', finishReason: FinishReason::Stop));

        $response = $driver->text($this->makeRequest());

        $this->assertSame('Hello back!', $response->text);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
    }

    public function testMultipleQueuedResponses(): void
    {
        $driver = new FakeDriver();
        $driver->willReturn(
            new TextResponse(text: 'First', finishReason: FinishReason::Stop),
            new TextResponse(text: 'Second', finishReason: FinishReason::Stop),
        );

        $r1 = $driver->text($this->makeRequest());
        $r2 = $driver->text($this->makeRequest());

        $this->assertSame('First', $r1->text);
        $this->assertSame('Second', $r2->text);
    }

    public function testRecordsRequests(): void
    {
        $driver = new FakeDriver();
        $driver->willReturn(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));

        $driver->text($this->makeRequest('Test prompt'));

        $this->assertCount(1, $driver->getRecorded());
    }

    public function testAssertRequestCount(): void
    {
        $driver = new FakeDriver();
        $driver->willReturn(
            new TextResponse(text: 'A', finishReason: FinishReason::Stop),
            new TextResponse(text: 'B', finishReason: FinishReason::Stop),
        );

        $driver->text($this->makeRequest());
        $driver->text($this->makeRequest());

        $driver->assertRequestCount(2);
        $this->assertTrue(true); // FakeDriver performs internal assertion
    }

    public function testAssertPromptContains(): void
    {
        $driver = new FakeDriver();
        $driver->willReturn(new TextResponse(text: 'OK', finishReason: FinishReason::Stop));

        $driver->text($this->makeRequest('Find weather in Madrid'));

        $driver->assertPromptContains('Madrid');
        $this->assertTrue(true); // FakeDriver performs internal assertion
    }

    public function testThrowsWhenQueueEmpty(): void
    {
        $driver = new FakeDriver();

        $this->expectException(\LogicException::class);
        $driver->text($this->makeRequest());
    }

    public function testWillReturnForModel(): void
    {
        $driver = new FakeDriver();
        $driver->willReturnForModel('gpt-4o', new TextResponse(text: 'GPT4', finishReason: FinishReason::Stop));

        $request = new TextRequest(provider: 'fake', model: 'gpt-4o', messages: [new UserMessage('Hi')]);
        $response = $driver->text($request);

        $this->assertSame('GPT4', $response->text);
    }

    public function testGetLastRecorded(): void
    {
        $driver = new FakeDriver();
        $driver->willReturn(
            new TextResponse(text: 'A', finishReason: FinishReason::Stop),
            new TextResponse(text: 'B', finishReason: FinishReason::Stop),
        );

        $driver->text($this->makeRequest('First'));
        $driver->text($this->makeRequest('Second'));

        $last = $driver->getLastRecorded();
        $this->assertNotNull($last);
    }
}
