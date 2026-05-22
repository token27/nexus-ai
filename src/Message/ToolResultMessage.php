<?php

declare(strict_types=1);

namespace Token27\NexusAI\Message;

use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\ValueObject\ToolResult;

/**
 * Message that sends tool execution results back to the LLM.
 *
 * Contains one ToolResult per ToolCall that was executed. The provider-specific
 * serialization format is handled by each driver's PayloadBuilder, not here.
 *
 * Provider format differences:
 * - OpenAI:    One message per result: {role: 'tool', tool_call_id: '...', content: '...'}
 * - Anthropic: Block inside user message: {role: 'user', content: [{type: 'tool_result', ...}]}
 *
 * @see \Token27\NexusAI\ValueObject\ToolResult
 * @see \Token27\NexusAI\ValueObject\ToolCall
 */
final class ToolResultMessage extends Message
{
    /**
     * @param array<ToolResult> $results Results of the executed tools, one per ToolCall.
     */
    public function __construct(
        public array $results,
    ) {
        // Content is the first result text for providers that expect a content field.
        $content = count($results) > 0 ? $results[0]->result : '';

        parent::__construct(
            role: MessageRole::Tool,
            content: $content,
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed> Serialized message with tool results.
     */
    public function jsonSerialize(): array
    {
        return [
            'role' => $this->role->value,
            'results' => array_map(
                fn (ToolResult $r): array => [
                    'call_id' => $r->callId,
                    'tool_name' => $r->toolName,
                    'result' => $r->result,
                    'is_error' => $r->isError,
                ],
                $this->results,
            ),
        ];
    }
}
