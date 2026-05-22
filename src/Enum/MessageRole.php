<?php

declare(strict_types=1);

namespace Token27\NexusAI\Enum;

/**
 * Role of a message in the conversation.
 *
 * Defines the participant type for each message in the chat history.
 * Used by all providers to determine how to handle each message.
 *
 * @see \Token27\NexusAI\Message\Message
 */
enum MessageRole: string
{
    /**
     * System instructions. Sent as the first message in the conversation.
     * Sets the behavior, personality, and constraints of the AI model.
     */
    case System = 'system';

    /**
     * Message from the human user.
     * Can contain text, images, audio, and other multi-modal content.
     */
    case User = 'user';

    /**
     * Response from the LLM.
     * Can contain text, tool calls, or both.
     */
    case Assistant = 'assistant';

    /**
     * Result of a tool execution.
     * OpenAI/Anthropic require this role explicitly for tool results.
     */
    case Tool = 'tool';
}
