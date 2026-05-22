<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Stream;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Stream\TextChunk;

final class TextChunkTest extends TestCase
{
    public function testTextContent(): void
    {
        $chunk = new TextChunk(text: 'Hello');
        $this->assertSame('Hello', $chunk->text);
        $this->assertSame('Hello', $chunk->getText());
    }

    public function testType(): void
    {
        $chunk = new TextChunk(text: 'Hi');
        $this->assertSame('text_delta', $chunk->getType());
    }

    public function testDefaultIndex(): void
    {
        $chunk = new TextChunk(text: 'X');
        $this->assertSame(0, $chunk->getIndex());
    }

    public function testFinishReason(): void
    {
        $chunk = new TextChunk(text: '', finishReason: FinishReason::Stop);
        $this->assertSame(FinishReason::Stop, $chunk->getFinishReason());
    }

    public function testNullFinishReasonForIntermediateChunk(): void
    {
        $chunk = new TextChunk(text: 'partial');
        $this->assertNull($chunk->getFinishReason());
    }
}
