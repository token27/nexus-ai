<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\ValueObject\ToolResult;

final class ToolResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = new ToolResult(
            callId: 'call_123',
            toolName: 'get_weather',
            result: '22°C sunny',
            isError: false,
        );

        $this->assertSame('call_123', $result->callId);
        $this->assertSame('get_weather', $result->toolName);
        $this->assertSame('22°C sunny', $result->result);
        $this->assertFalse($result->isError);
    }

    public function testErrorResult(): void
    {
        $result = new ToolResult(
            callId: 'call_456',
            toolName: 'search',
            result: 'Connection timeout',
            isError: true,
        );

        $this->assertTrue($result->isError);
    }
}
