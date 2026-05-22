<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Message;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\Message\AssistantMessage;

final class AssistantMessageTest extends TestCase
{
    public function testRole(): void
    {
        $msg = new AssistantMessage('Hello, human!');
        $this->assertSame(MessageRole::Assistant, $msg->getRole());
    }

    public function testTextContent(): void
    {
        $msg = new AssistantMessage('This is the response.');
        $this->assertSame('This is the response.', $msg->getText());
    }
}
