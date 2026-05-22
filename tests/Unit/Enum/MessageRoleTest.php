<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\MessageRole;

final class MessageRoleTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertSame('system', MessageRole::System->value);
        $this->assertSame('user', MessageRole::User->value);
        $this->assertSame('assistant', MessageRole::Assistant->value);
        $this->assertSame('tool', MessageRole::Tool->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(MessageRole::System, MessageRole::from('system'));
        $this->assertSame(MessageRole::User, MessageRole::from('user'));
        $this->assertSame(MessageRole::Assistant, MessageRole::from('assistant'));
        $this->assertSame(MessageRole::Tool, MessageRole::from('tool'));
    }
}
