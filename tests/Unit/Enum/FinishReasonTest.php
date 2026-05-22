<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;

final class FinishReasonTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertSame('stop', FinishReason::Stop->value);
        $this->assertSame('length', FinishReason::Length->value);
        $this->assertSame('tool_calls', FinishReason::ToolCalls->value);
        $this->assertSame('content_filter', FinishReason::ContentFilter->value);
        $this->assertSame('unknown', FinishReason::Unknown->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(FinishReason::Stop, FinishReason::from('stop'));
        $this->assertSame(FinishReason::Length, FinishReason::from('length'));
        $this->assertSame(FinishReason::ToolCalls, FinishReason::from('tool_calls'));
    }

    public function testTryFromUnknown(): void
    {
        $this->assertNull(FinishReason::tryFrom('nonexistent'));
    }
}
