<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\ValueObject\RateLimitInfo;

final class RateLimitInfoTest extends TestCase
{
    public function testConstruction(): void
    {
        $info = new RateLimitInfo(
            name: 'requests',
            limit: 100,
            remaining: 50,
        );

        $this->assertSame('requests', $info->name);
        $this->assertSame(100, $info->limit);
        $this->assertSame(50, $info->remaining);
    }

    public function testNullableFields(): void
    {
        $info = new RateLimitInfo(name: 'tokens');
        $this->assertNull($info->limit);
        $this->assertNull($info->remaining);
        $this->assertNull($info->resetsAt);
    }

    public function testWithResetsAt(): void
    {
        $reset = new \DateTimeImmutable('2024-01-01T00:00:00Z');
        $info = new RateLimitInfo(name: 'requests', resetsAt: $reset);
        $this->assertSame($reset, $info->resetsAt);
    }
}
