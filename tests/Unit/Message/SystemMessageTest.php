<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Message;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\Message\SystemMessage;

final class SystemMessageTest extends TestCase
{
    public function testRole(): void
    {
        $msg = new SystemMessage('You are a helpful assistant.');
        $this->assertSame(MessageRole::System, $msg->getRole());
    }

    public function testContent(): void
    {
        $msg = new SystemMessage('Be concise.');
        $this->assertSame('Be concise.', $msg->getText());
    }
}
