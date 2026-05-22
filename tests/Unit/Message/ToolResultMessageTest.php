<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Message;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\Message\ToolResultMessage;
use Token27\NexusAI\ValueObject\ToolResult;

final class ToolResultMessageTest extends TestCase
{
    public function testRole(): void
    {
        $result = new ToolResult(
            callId: 'call_123',
            toolName: 'get_weather',
            result: 'Sunny 22°C',
        );

        $msg = new ToolResultMessage([$result]);
        $this->assertSame(MessageRole::Tool, $msg->getRole());
    }

    public function testResults(): void
    {
        $r1 = new ToolResult(callId: 'c1', toolName: 't1', result: 'r1');
        $r2 = new ToolResult(callId: 'c2', toolName: 't2', result: 'r2');

        $msg = new ToolResultMessage([$r1, $r2]);
        $this->assertCount(2, $msg->results);
    }
}
