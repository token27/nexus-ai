<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Event;

/**
 * Event emitted just before a tool is executed.
 *
 * Allows observers to monitor, log, or audit tool invocations
 * before they run.
 *
 * @see \Token27\NexusAI\Observability\EventBus
 */
final readonly class ToolCalling
{
    /**
     * @param string $toolName Name of the tool about to be called.
     * @param array<string, mixed> $arguments Arguments passed to the tool.
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
    ) {
    }
}
