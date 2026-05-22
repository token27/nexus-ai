<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

/**
 * Contract for tools that the LLM can invoke during a conversation.
 *
 * Unlike Neuron AI's mutable ToolInterface (setInputs/setResult), NexusAI tools
 * are stateless: inputs are passed as arguments to execute(), and the result is
 * returned. This design is safe for parallel tool execution.
 *
 * @see \Token27\NexusAI\ValueObject\ToolCall
 * @see \Token27\NexusAI\ValueObject\ToolResult
 */
interface ToolInterface
{
    /**
     * Returns the unique name of the tool.
     *
     * The LLM uses this name to invoke the tool (e.g., 'get_weather').
     *
     * @return string The tool name.
     */
    public function getName(): string;

    /**
     * Returns a description for the LLM explaining what this tool does.
     *
     * Example: 'Gets current weather for a location'.
     *
     * @return string The tool description.
     */
    public function getDescription(): string;

    /**
     * Returns the JSON Schema of the input parameters.
     *
     * Must follow JSON Schema format with 'type', 'properties', 'required'.
     *
     * @return array<string, mixed> JSON Schema for the tool's parameters.
     */
    public function getParameters(): array;

    /**
     * Executes the tool with the given arguments parsed from the LLM response.
     *
     * Returns the result as a string because the LLM only understands text.
     * Complex data should be serialized to a JSON string.
     *
     * @param array<string, mixed> $arguments The arguments from the LLM.
     * @return string The execution result as text.
     */
    public function execute(array $arguments): string;
}
