<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\ValueObject\ToolCall;

final class ToolCallTest extends TestCase
{
    public function testConstruction(): void
    {
        $tc = new ToolCall(
            id: 'call_123',
            name: 'get_weather',
            arguments: ['city' => 'Madrid', 'unit' => 'celsius'],
        );

        $this->assertSame('call_123', $tc->id);
        $this->assertSame('get_weather', $tc->name);
        $this->assertSame(['city' => 'Madrid', 'unit' => 'celsius'], $tc->arguments);
    }

    public function testEmptyArguments(): void
    {
        $tc = new ToolCall(id: 'call_456', name: 'list_files', arguments: []);
        $this->assertSame([], $tc->arguments);
    }
}
