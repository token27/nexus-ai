<?php

declare(strict_types=1);

namespace Token27\NexusAI\Request;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Contract\ToolInterface;
use Token27\NexusAI\Enum\StructuredMode;
use Token27\NexusAI\Enum\ToolChoice;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\SystemMessage;

/**
 * Value Object for structured output requests.
 *
 * Like TextRequest but adds schema-related properties for forcing the LLM
 * to return JSON conforming to a specific structure, which is then
 * deserialized into a typed PHP DTO.
 *
 * Contains all TextRequest properties PLUS:
 * - outputClass: FQCN of the target DTO (e.g., ArticleDTO::class)
 * - mode: Strategy for enforcing JSON output (Auto, Json, Tool, Native)
 * - maxRetries: Retry count when output validation fails
 *
 * @see \Token27\NexusAI\Contract\DriverInterface::structured()
 * @see \Token27\NexusAI\Enum\StructuredMode
 */
final readonly class StructuredRequest implements RequestInterface
{
    /**
     * @param string $provider Provider key (e.g., 'openai', 'anthropic').
     * @param string $model Model identifier (e.g., 'gpt-4o').
     * @param string $outputClass FQCN of the DTO class. SchemaGenerator uses reflection to generate JSON Schema.
     * @param array<Message> $messages Conversation messages.
     * @param string|null $systemPrompt System prompt shortcut.
     * @param StructuredMode $mode Strategy for forcing JSON output.
     * @param int $maxRetries If output fails validation, retry up to N times with error feedback.
     * @param int|null $maxTokens Maximum tokens to generate.
     * @param float|null $temperature Creativity 0.0-2.0.
     * @param float|null $topP Nucleus sampling.
     * @param array<ToolInterface> $tools Tools available for the LLM.
     * @param ToolChoice|null $toolChoice How the LLM should use tools.
     * @param int $maxSteps Maximum tool-call→result cycles.
     * @param array<string, mixed> $options Passthrough provider-specific options.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $outputClass,
        public array $messages = [],
        public ?string $systemPrompt = null,
        public StructuredMode $mode = StructuredMode::Auto,
        public int $maxRetries = 3,
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
