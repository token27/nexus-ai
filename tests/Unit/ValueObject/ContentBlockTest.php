<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\ContentType;
use Token27\NexusAI\ValueObject\ContentBlock;

final class ContentBlockTest extends TestCase
{
    public function testTextBlock(): void
    {
        $block = ContentBlock::text('Hello world');
        $this->assertSame(ContentType::Text, $block->type);
        $this->assertSame('Hello world', $block->content);
        $this->assertNull($block->sourceType);
        $this->assertNull($block->mediaType);
    }

    public function testImageUrlBlock(): void
    {
        $block = ContentBlock::imageUrl('https://example.com/img.png');
        $this->assertSame(ContentType::Image, $block->type);
        $this->assertSame('https://example.com/img.png', $block->content);
        $this->assertSame('url', $block->sourceType);
    }

    public function testImageBase64Block(): void
    {
        $block = ContentBlock::imageBase64('iVBORw0KGgo=', 'image/png');
        $this->assertSame(ContentType::Image, $block->type);
        $this->assertSame('iVBORw0KGgo=', $block->content);
        $this->assertSame('base64', $block->sourceType);
        $this->assertSame('image/png', $block->mediaType);
    }
}
