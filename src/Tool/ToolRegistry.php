<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

use Token27\NexusAI\Contract\ToolInterface;
use Token27\NexusAI\Exception\ToolException;

/**
 * Collection of registered tools, indexed by name.
 *
 * Provides lookup by name for the ToolExecutor, and schema generation
 * for the PayloadBuilder to include tool definitions in API requests.
 *
 * @see \Token27\NexusAI\Tool\ToolExecutor
 * @see \Token27\NexusAI\Contract\ToolInterface
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> Map of tool name → tool instance. */
    private array $tools = [];

    /**
     * Registers a single tool by its name.
     *
     * @param ToolInterface $tool The tool to register.
     */
    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * Registers multiple tools at once.
     *
     * @param array<ToolInterface> $tools The tools to register.
     */
    public function registerMany(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    /**
     * Retrieves a tool by name.
     *
     * @param string $name The tool name to look up.
     * @return ToolInterface The found tool.
     *
     * @throws ToolException If the tool is not registered.
     */
    public function get(string $name): ToolInterface
    {
        if (!isset($this->tools[$name])) {
            throw new ToolException(
                message: "Tool not found: {$name}",
                toolName: $name,
            );
        }

        return $this->tools[$name];
    }

    /**
     * Checks if a tool is registered by name.
     *
     * @param string $name The tool name to check.
     * @return bool True if registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Returns all registered tools.
     *
     * @return array<ToolInterface> All registered tool instances.
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Generates the array of tool definitions for the API payload.
     *
     * Each tool becomes: {type: 'function', function: {name, description, parameters}}
     * This output is consumed by the PayloadBuilder.
     *
     * @return array<int, array<string, mixed>> Array of tool schema definitions.
     */
    public function toSchemaArray(): array
    {
        return array_map(
            fn (ToolInterface $tool): array => [
                'type' => 'function',
                'function' => array_filter([
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters() !== [] ? $tool->getParameters() : null,
                ]),
            ],
            $this->all(),
        );
    }
}
