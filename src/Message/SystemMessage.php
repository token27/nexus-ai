<?php

declare(strict_types=1);

namespace Token27\NexusAI\Message;

use Token27\NexusAI\Enum\MessageRole;

/**
 * System instructions message. Always goes first in the conversation.
 *
 * Sets the behavior, personality, and constraints of the AI model.
 * System messages are always plain text (no multi-modal content).
 *
 * @see \Token27\NexusAI\Enum\MessageRole::System
 */
final class SystemMessage extends Message
{
    /**
     * @param string $content The system instruction text.
     */
    public function __construct(string $content)
    {
        parent::__construct(
            role: MessageRole::System,
            content: $content,
        );
    }
}
