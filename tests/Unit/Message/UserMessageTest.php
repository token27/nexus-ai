<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Message;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\ValueObject\ContentBlock;

final class UserMessageTest extends TestCase
{
    public function testTextContent(): void
    {
        $msg = new UserMessage('Hello, AI!');
        $this->assertSame(MessageRole::User, $msg->getRole());
        $this->assertSame('Hello, AI!', $msg->getText());
    }

    public function testContentBlockArray(): void
    {
        $blocks = [
            ContentBlock::text('Describe this image'),
            ContentBlock::imageUrl('https://example.com/img.png'),
        ];

        $msg = new UserMessage($blocks);
        $this->assertSame(MessageRole::User, $msg->getRole());
    }

    public function testSingleContentBlock(): void
    {
        $block = ContentBlock::text('A single block');
        $msg = new UserMessage($block);
        $this->assertSame(MessageRole::User, $msg->getRole());
    }
}
