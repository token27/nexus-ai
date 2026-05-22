<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

use Token27\NexusAI\Observability\Event\ToolCalled;
use Token27\NexusAI\Observability\Event\ToolCalling;
use Token27\NexusAI\Observability\EventBus;
use Token27\NexusAI\ValueObject\ToolCall;
use Token27\NexusAI\ValueObject\ToolResult;

/**
 * Executes tool calls returned by the LLM.
 *
 * For each ToolCall, looks up the corresponding tool in the registry,
 * executes it, and returns a ToolResult. Captures exceptions gracefully
 * and returns error results instead of propagating failures.
 *
 * Emits ToolCalling (before) and ToolCalled (after) events via EventBus
 * for observability. Supports optional parallel execution via pcntl.
 *
 * @see \Token27\NexusAI\Tool\ToolRegistry
 * @see \Token27\NexusAI\ValueObject\ToolCall
 * @see \Token27\NexusAI\ValueObject\ToolResult
 */
final class ToolExecutor
{
    /**
     * @param ToolRegistry $registry The registry containing available tools.
     * @param EventBus|null $eventBus Optional event bus for ToolCalling/ToolCalled events.
     * @param bool $parallel Whether to attempt parallel execution via pcntl.
     */
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ?EventBus $eventBus = null,
        private readonly bool $parallel = false,
    ) {
    }

    /**
     * Executes an array of tool calls and returns their results.
     *
     * For each ToolCall:
     * 1. Look up the tool in the registry
     * 2. If not found → ToolResult with isError=true
     * 3. Emit ToolCalling event
     * 4. Execute the tool (catching any Throwable)
     * 5. Emit ToolCalled event with timing
     * 6. Return ToolResult
     *
     * @param array<ToolCall> $toolCalls The tool calls from the LLM response.
     * @return array<ToolResult> Results for each tool call, in the same order.
     */
    public function execute(array $toolCalls): array
    {
        // Attempt parallel execution if enabled and available
        if ($this->parallel && extension_loaded('pcntl') && count($toolCalls) > 1) {
            return $this->executeParallel($toolCalls);
        }

        return $this->executeSequential($toolCalls);
    }

    /**
     * Executes tool calls sequentially.
     *
     * @param array<ToolCall> $toolCalls The tool calls to execute.
     * @return array<ToolResult> Results for each tool call.
     */
    private function executeSequential(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $this->executeSingle($toolCall);
        }

        return $results;
    }

    /**
     * Executes a single tool call with event emission and error handling.
     *
     * @param ToolCall $toolCall The tool call to execute.
     * @return ToolResult The execution result.
     */
    private function executeSingle(ToolCall $toolCall): ToolResult
    {
        $name = $toolCall->name;

        // Check if tool exists
        if (!$this->registry->has($name)) {
            return new ToolResult(
                callId: $toolCall->id,
                toolName: $name,
                result: "Tool not found: {$name}",
                isError: true,
            );
        }

        $tool = $this->registry->get($name);

        // Emit pre-execution event
        $this->eventBus?->emit('tool.calling', $this, new ToolCalling(
            toolName: $name,
            arguments: $toolCall->arguments,
        ));

        // Execute with timing and error capture
        $startTime = hrtime(true);

        try {
            $result = $tool->execute($toolCall->arguments);
            $isError = false;
        } catch (\Throwable $e) {
            $result = "Error executing {$name}: " . $e->getMessage();
            $isError = true;
        }

        $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;

        // Emit post-execution event
        $this->eventBus?->emit('tool.called', $this, new ToolCalled(
            toolName: $name,
            arguments: $toolCall->arguments,
            result: $result,
            elapsedMs: $elapsedMs,
            isError: $isError,
        ));

        return new ToolResult(
            callId: $toolCall->id,
            toolName: $name,
            result: $result,
            isError: $isError,
        );
    }

    /**
     * Executes tool calls in parallel using pcntl_fork.
     *
     * Each tool runs in a child process. Results are communicated via
     * temporary files with serialized ToolResult objects. Falls back to
     * sequential execution if forking fails.
     *
     * @param array<ToolCall> $toolCalls The tool calls to execute in parallel.
     * @return array<ToolResult> Results for each tool call.
     */
    private function executeParallel(array $toolCalls): array
    {
        /** @var array<int, string> Map of child PID → temp file path */
        $children = [];
        /** @var array<int, int> Map of index → child PID */
        $pidMap = [];

        foreach ($toolCalls as $index => $toolCall) {
            $tempFile = tempnam(sys_get_temp_dir(), 'nexus_tool_');
            if ($tempFile === false) {
                // Fallback to sequential if temp file creation fails
                return $this->executeSequential($toolCalls);
            }

            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed — fallback to sequential
                @unlink($tempFile);
                return $this->executeSequential($toolCalls);
            }

            if ($pid === 0) {
                // Child process: execute tool and write result to temp file
                $result = $this->executeSingle($toolCall);
                file_put_contents($tempFile, serialize($result));
                exit(0);
            }

            // Parent process: track child
            $children[$pid] = $tempFile;
            $pidMap[$index] = $pid;
        }

        // Parent: wait for all children
        foreach ($children as $pid => $tempFile) {
            pcntl_waitpid($pid, $status);
        }

        // Read results from temp files in original order
        $results = [];
        foreach ($toolCalls as $index => $toolCall) {
            $pid = $pidMap[$index];
            $tempFile = $children[$pid];

            if (file_exists($tempFile)) {
                $data = file_get_contents($tempFile);
                if ($data !== false) {
                    $result = unserialize($data);
                    if ($result instanceof ToolResult) {
                        $results[] = $result;
                        @unlink($tempFile);
                        continue;
                    }
                }
                @unlink($tempFile);
            }

            // Fallback if deserialization failed
            $results[] = new ToolResult(
                callId: $toolCall->id,
                toolName: $toolCall->name,
                result: 'Parallel execution failed for tool: ' . $toolCall->name,
                isError: true,
            );
        }

        return $results;
    }
}
