<?php

declare(strict_types=1);

namespace Token27\NexusAI\ValueObject;

/**
 * Result of executing a tool.
 *
 * Contains the execution output (or error) that gets sent back to the LLM.
 * The callId must match the corresponding ToolCall::$id for proper correlation.
 *
 * @see \Token27\NexusAI\ValueObject\ToolCall
 * @see \Token27\NexusAI\Message\ToolResultMessage
 */
readonly class ToolResult
{
    /**
     * @param string $callId ID of the ToolCall this responds to. Must match ToolCall::$id.
     * @param string $toolName Name of the tool that was executed.
     * @param string $result Result as text. Complex data should be JSON-serialized since the LLM only understands text.
     * @param bool $isError True if execution failed. The LLM receives the error as feedback to retry.
     */
    public function __construct(
        public string $callId,
        public string $toolName,
        public string $result,
        public bool $isError = false,
    ) {
    }
}
