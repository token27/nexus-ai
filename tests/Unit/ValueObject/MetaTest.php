<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\ValueObject\Meta;

final class MetaTest extends TestCase
{
    public function testDefaults(): void
    {
        $meta = new Meta();
        $this->assertNull($meta->id);
        $this->assertNull($meta->model);
        $this->assertSame([], $meta->rateLimits);
    }

    public function testWithValues(): void
    {
        $meta = new Meta(
            id: 'chatcmpl-abc',
            model: 'gpt-4o-2024-05-13',
            rateLimits: [],
        );

        $this->assertSame('chatcmpl-abc', $meta->id);
        $this->assertSame('gpt-4o-2024-05-13', $meta->model);
    }
}
