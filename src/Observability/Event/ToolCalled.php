<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Event;

/**
 * Event emitted just after a tool has been executed.
 *
 * Contains the tool name, arguments, result, timing, and whether
 * the execution produced an error.
 *
 * @see \Token27\NexusAI\Observability\EventBus
 */
final readonly class ToolCalled
{
    /**
     * @param string $toolName Name of the tool that was called.
     * @param array<string, mixed> $arguments Arguments that were passed to the tool.
     * @param string $result The result returned by the tool.
     * @param float $elapsedMs Time taken to execute the tool in milliseconds.
     * @param bool $isError Whether the tool execution produced an error.
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public string $result,
        public float $elapsedMs,
        public bool $isError = false,
    ) {
    }
}
