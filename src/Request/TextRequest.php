<?php

declare(strict_types=1);

namespace Token27\NexusAI\Request;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Contract\ToolInterface;
use Token27\NexusAI\Enum\ToolChoice;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\SystemMessage;

/**
 * Value Object containing all parameters for a chat completion request.
 *
 * Constructed via the fluent builder (PendingRequest) and passed to the driver.
 * All properties are immutable (readonly class).
 *
 * Unlike Prism which uses 15+ traits to accumulate options, NexusAI uses a
 * single readonly class with all properties — simpler, more legible, easier to debug.
 *
 * @see \Token27\NexusAI\PendingRequest
 * @see \Token27\NexusAI\Contract\DriverInterface::text()
 */
final readonly class TextRequest implements RequestInterface
{
    /**
     * @param string $provider Provider key (e.g., 'openai', 'anthropic').
     * @param string $model Model identifier (e.g., 'gpt-4o', 'claude-sonnet-4-20250514').
     * @param array<Message> $messages Conversation messages (system, user, assistant).
     * @param string|null $systemPrompt System prompt shortcut. Converted to SystemMessage by resolveMessages().
     * @param int|null $maxTokens Maximum tokens to generate. Null = provider default.
     * @param float|null $temperature Creativity 0.0-2.0. Null = provider default (usually 1.0).
     * @param float|null $topP Nucleus sampling. Alternative to temperature.
     * @param array<ToolInterface> $tools Tools available for the LLM to call.
     * @param ToolChoice|null $toolChoice How the LLM should use tools.
     * @param int $maxSteps Maximum tool-call→result cycles. 1 = no tool loop.
     * @param array<string, mixed> $options Passthrough provider-specific options.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public array $messages = [],
        public ?string $systemPrompt = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public array $tools = [],
        public ?ToolChoice $toolChoice = null,
        public int $maxSteps = 1,
        public array $options = [],
    ) {
    }

    /** {@inheritdoc} */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /** {@inheritdoc} */
    public function getModel(): string
    {
        return $this->model;
    }

    /** {@inheritdoc} */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** {@inheritdoc} */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** {@inheritdoc} */
    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    /** {@inheritdoc} */
    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    /**
     * Resolves the final messages array including the system prompt shortcut.
     *
     * If systemPrompt is set, it is prepended as a SystemMessage to the
     * messages array. This allows using systemPrompt as a convenience
     * shortcut without manually constructing the messages array.
     *
     * @return array<Message> The resolved messages with system prompt prepended (if set).
     */
    public function resolveMessages(): array
    {
        $resolved = [];

        if ($this->systemPrompt !== null) {
            $resolved[] = new SystemMessage($this->systemPrompt);
        }

        return [...$resolved, ...$this->messages];
    }
}
