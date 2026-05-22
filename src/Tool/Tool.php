<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tool;

use Token27\NexusAI\Contract\ToolInterface;

/**
 * Base implementation of a tool that the LLM can invoke.
 *
 * Two usage patterns:
 *
 * 1. **Inheritance** — extend this class, override execute():
 *    ```php
 *    class WeatherTool extends Tool {
 *        protected string $name = 'get_weather';
 *        protected string $description = 'Gets weather';
 *        public function execute(array $arguments): string { ... }
 *    }
 *    ```
 *
 * 2. **Factory** — use make() with a callable:
 *    ```php
 *    $tool = Tool::make('get_weather', 'Gets weather')
 *        ->addProperty(new ToolProperty('city', PropertyType::String, 'City', required: true))
 *        ->setCallable(fn(array $args) => "22°C in {$args['city']}");
 *    ```
 *
 * Unlike Neuron AI's mutable Tool (setInputs/setResult), NexusAI tools are
 * stateless: inputs are passed as arguments, results are returned. This is
 * safe for parallel execution.
 *
 * @see \Token27\NexusAI\Contract\ToolInterface
 * @see \Token27\NexusAI\Tool\ToolProperty
 */
abstract class Tool implements ToolInterface
{
    /** @var string Tool name used by the LLM to invoke it. */
    protected string $name = '';

    /** @var string Description explaining what this tool does. */
    protected string $description = '';

    /** @var array<ToolProperty|ArrayProperty|ObjectProperty> Parameter definitions. */
    protected array $properties = [];

    /** @var callable|null Callable implementation for factory-created tools. */
    protected $callable = null;

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     *
     * Builds JSON Schema from $this->properties array.
     * Returns empty array if no properties are defined.
     *
     * @return array<string, mixed> JSON Schema with type 'object', properties, and required.
     */
    public function getParameters(): array
    {
        if ($this->properties === []) {
            return [];
        }

        $props = [];
        $required = [];

        foreach ($this->properties as $prop) {
            $props[$prop->name] = $prop->toSchema();

            if ($prop->required) {
                $required[] = $prop->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $props,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * {@inheritdoc}
     *
     * Default implementation delegates to the callable set via setCallable().
     * Subclasses should override this method with their own logic.
     *
     * @param array<string, mixed> $arguments Arguments from the LLM.
     * @return string Execution result as text.
     *
     * @throws \BadMethodCallException If no callable is set and method is not overridden.
     */
    public function execute(array $arguments): string
    {
        if ($this->callable !== null) {
            return ($this->callable)($arguments);
        }

        throw new \BadMethodCallException(
            'Tool "' . $this->name . '" must override execute() or use setCallable().',
        );
    }

    /**
     * Factory method to create a tool without subclassing.
     *
     * Use addProperty() and setCallable() to configure the tool.
     *
     * @param string $name Tool name for the LLM.
     * @param string $description Tool description for the LLM.
     * @return self A concrete Tool instance ready for configuration.
     */
    public static function make(string $name, string $description): self
    {
        return new class ($name, $description) extends Tool {
            public function __construct(string $name, string $description)
            {
                $this->name = $name;
                $this->description = $description;
            }
        };
    }

    /**
     * Adds a property (parameter) definition to this tool.
     *
     * @param ToolProperty|ArrayProperty|ObjectProperty $property The property to add.
     * @return $this For fluent chaining.
     */
    public function addProperty(ToolProperty|ArrayProperty|ObjectProperty $property): static
    {
        $this->properties[] = $property;

        return $this;
    }

    /**
     * Sets a callable as the execute() implementation (factory pattern).
     *
     * The callable receives array<string, mixed> and must return string.
     *
     * @param callable(array<string, mixed>): string $fn The callable implementation.
     * @return $this For fluent chaining.
     */
    public function setCallable(callable $fn): static
    {
        $this->callable = $fn;

        return $this;
    }
}
