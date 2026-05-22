<?php

declare(strict_types=1);

namespace Token27\NexusAI\Message;

use Token27\NexusAI\ValueObject\ToolCall;

/**
 * Specialized message when the LLM ONLY returns tool calls (no text content).
 *
 * Extends AssistantMessage because in the provider protocol, tool calls
 * come as messages from the assistant role.
 *
 * @see \Token27\NexusAI\Message\AssistantMessage
 * @see \Token27\NexusAI\ValueObject\ToolCall
 */
final class ToolCallMessage extends AssistantMessage
{
    /**
     * @param array<ToolCall> $toolCalls The tool calls from the LLM.
     */
    public function __construct(array $toolCalls)
    {
        parent::__construct(
            content: '',
            toolCalls: $toolCalls,
        );
    }
}
