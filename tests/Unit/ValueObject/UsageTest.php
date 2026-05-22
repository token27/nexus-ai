<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\ValueObject\Usage;

final class UsageTest extends TestCase
{
    public function testTotalTokens(): void
    {
        $usage = new Usage(textInputTokens: 100, textOutputTokens: 50);
        $this->assertSame(150, $usage->totalTokens());
    }

    public function testZeroTokens(): void
    {
        $usage = new Usage(textInputTokens: 0, textOutputTokens: 0);
        $this->assertSame(0, $usage->totalTokens());
    }

    public function testGetters(): void
    {
        $usage = new Usage(textInputTokens: 200, textOutputTokens: 300);
        $this->assertSame(200, $usage->textInputTokens);
        $this->assertSame(300, $usage->textOutputTokens);
    }

    public function testCacheTokens(): void
    {
        $usage = new Usage(100, 50, cacheWriteTokens: 20, cacheReadTokens: 10);
        $this->assertSame(20, $usage->cacheWriteTokens);
        $this->assertSame(10, $usage->cacheReadTokens);
    }

    public function testCacheTokensDefaultNull(): void
    {
        $usage = new Usage(100, 50);
        $this->assertNull($usage->cacheWriteTokens);
        $this->assertNull($usage->cacheReadTokens);
    }
}
