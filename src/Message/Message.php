<?php

declare(strict_types=1);

namespace Token27\NexusAI\Message;

use JsonSerializable;
use Token27\NexusAI\Enum\ContentType;
use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\ValueObject\ContentBlock;

/**
 * Abstract base class for all message types in a conversation.
 *
 * Provides the common structure (role, content, metadata) and methods
 * shared by SystemMessage, UserMessage, AssistantMessage, ToolCallMessage,
 * and ToolResultMessage.
 *
 * Implements JsonSerializable for easy serialization to JSON format.
 *
 * @see \Token27\NexusAI\Enum\MessageRole
 * @see \Token27\NexusAI\ValueObject\ContentBlock
 */
abstract class Message implements JsonSerializable
{
    /**
     * @param MessageRole $role The role of this message (System/User/Assistant/Tool).
     * @param string|array<ContentBlock> $content Plain text or array of multi-modal content blocks.
     * @param array<string, mixed> $metadata Free key-value data for additional information.
     */
    public function __construct(
        protected readonly MessageRole $role,
        protected readonly string|array $content,
        protected readonly array $metadata = [],
    ) {
    }

    /**
     * Returns the role of this message.
     *
     * @return MessageRole The message role.
     */
    public function getRole(): MessageRole
    {
        return $this->role;
    }

    /**
     * Returns the raw content (text string or ContentBlock array).
     *
     * @return string|array<ContentBlock> The message content.
     */
    public function getContent(): string|array
    {
        return $this->content;
    }

    /**
     * Extracts only the text content.
     *
     * If content is a string, returns it directly.
     * If content is an array of ContentBlocks, concatenates all Text-type blocks.
     *
     * @return string The text content.
     */
    public function getText(): string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        $texts = [];
        foreach ($this->content as $block) {
            if ($block->type === ContentType::Text) {
                $texts[] = $block->content;
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Returns the message metadata.
     *
     * @return array<string, mixed> The metadata key-value pairs.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Checks if this message contains multi-modal content (non-text blocks).
     *
     * @return bool True if content is an array with at least one non-text block.
     */
    public function isMultiModal(): bool
    {
        if (is_string($this->content)) {
            return false;
        }

        foreach ($this->content as $block) {
            if ($block->type !== ContentType::Text) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serializes the message for JSON encoding.
     *
     * @return array<string, mixed> The serializable representation.
     */
    public function jsonSerialize(): array
    {
        if (is_string($this->content)) {
            return [
                'role' => $this->role->value,
                'content' => $this->content,
            ];
        }

        return [
            'role' => $this->role->value,
            'content' => array_map(
                fn (ContentBlock $block): array => [
                    'type' => $block->type->value,
                    'content' => $block->content,
                    'source_type' => $block->sourceType,
                    'media_type' => $block->mediaType,
                ],
                $this->content,
            ),
        ];
    }
}
