<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * Error during the execution of a tool.
 *
 * Contains the name of the tool that failed for debugging and error reporting.
 *
 * @see \Token27\NexusAI\Contract\ToolInterface
 * @see \Token27\NexusAI\Tool\ToolExecutor
 */
class ToolException extends NexusException
{
    /**
     * @param string $message Human-readable error message.
     * @param string $toolName Name of the tool that failed.
     * @param \Throwable|null $previous Previous exception if wrapping.
     */
    public function __construct(
        string $message,
        public readonly string $toolName,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
