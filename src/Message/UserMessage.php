<?php

declare(strict_types=1);

namespace Token27\NexusAI\Message;

use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\ValueObject\ContentBlock;

/**
 * Message from the human user. Supports multi-modal content (text + images + audio).
 *
 * Accepts three input formats for convenience:
 * - Plain string: stored directly as text content.
 * - Single ContentBlock: wrapped in an array.
 * - Array of ContentBlocks: stored directly for multi-modal messages.
 *
 * Usage examples:
 *   new UserMessage('Explain quantum physics')
 *   new UserMessage([ContentBlock::text('What is this?'), ContentBlock::imageUrl('https://...')])
 *
 * @see \Token27\NexusAI\ValueObject\ContentBlock
 */
final class UserMessage extends Message
{
    /**
     * @param string|ContentBlock|array<ContentBlock> $content Text, single block, or array of multi-modal blocks.
     */
    public function __construct(string|ContentBlock|array $content)
    {
        if ($content instanceof ContentBlock) {
            $content = [$content];
        }

        parent::__construct(
            role: MessageRole::User,
            content: $content,
        );
    }
}
