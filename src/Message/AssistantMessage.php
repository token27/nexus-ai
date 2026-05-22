<?php

declare(strict_types=1);

namespace Token27\NexusAI\Message;

use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\ValueObject\ContentBlock;
use Token27\NexusAI\ValueObject\ToolCall;

/**
 * Response from the LLM. Can contain text, tool calls, or both.
 *
 * NOT final because ToolCallMessage extends this class (tool calls are
 * semantically assistant messages in the provider protocol).
 *
 * @see \Token27\NexusAI\Message\ToolCallMessage
 * @see \Token27\NexusAI\ValueObject\ToolCall
 */
class AssistantMessage extends Message
{
    /**
     * @param string|array<ContentBlock> $content Response content (text or content blocks).
     * @param array<ToolCall> $toolCalls Tool calls requested by the LLM. Empty if no tool calls.
     * @param Usage|null $usage Token usage for this specific response.
     */
    public function __construct(
        string|array $content,
        public array $toolCalls = [],
        public ?Usage $usage = null,
    ) {
        parent::__construct(
            role: MessageRole::Assistant,
            content: $content,
        );
    }

    /**
     * Checks if the LLM requested tool calls.
     *
     * @return bool True if there are pending tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Returns the tool calls requested by the LLM.
     *
     * @return array<ToolCall> The tool calls.
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Returns the token usage for this response.
     *
     * @return Usage|null The usage data, or null if not available.
     */
    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed> Serialized message including tool calls if present.
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        if ($this->hasToolCalls()) {
            $data['tool_calls'] = array_map(
                fn (ToolCall $tc): array => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => json_encode($tc->arguments, JSON_THROW_ON_ERROR),
                    ],
                ],
                $this->toolCalls,
            );
        }

        return $data;
    }
}
