<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Stream;

use Generator;
use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Response\StreamResponse;
use Token27\NexusAI\Stream\TextChunk;
use Token27\NexusAI\Stream\UsageChunk;

final class StreamResponseTest extends TestCase
{
    public function testCollectConcatenatesText(): void
    {
        $gen = function (): Generator {
            yield new TextChunk(text: 'Hello');
            yield new TextChunk(text: ' world!', finishReason: FinishReason::Stop);
        };
        $response = (new StreamResponse($gen()))->collect();
        $this->assertSame('Hello world!', $response->text);
    }

    public function testCollectCapturesUsage(): void
    {
        $gen = function (): Generator {
            yield new TextChunk(text: 'Hi');
            yield new UsageChunk(usage: new Usage(10, 5), index: 0);
        };
        $response = (new StreamResponse($gen()))->collect();
        $this->assertSame(10, $response->usage->textInputTokens);
    }

    public function testTextGeneratorYieldsOnlyText(): void
    {
        $gen = function (): Generator {
            yield new TextChunk(text: 'A');
            yield new UsageChunk(usage: new Usage(1, 1), index: 0);
            yield new TextChunk(text: 'B');
        };
        $texts = iterator_to_array((new StreamResponse($gen()))->text());
        $this->assertSame(['A', 'B'], array_values($texts));
    }
}
