<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Message\Message;

/**
 * Base contract shared by all request types (Text, Structured, Embedding, Image, Audio).
 *
 * Provides the common properties that every request to an AI provider must have:
 * provider key, model name, messages, options, and generation parameters.
 *
 * @see \Token27\NexusAI\Request\TextRequest
 * @see \Token27\NexusAI\Request\StructuredRequest
 * @see \Token27\NexusAI\Request\EmbeddingRequest
 */
interface RequestInterface
{
    /**
     * Returns the provider key.
     *
     * @return string Provider identifier (e.g., 'openai', 'anthropic', 'gemini').
     */
    public function getProvider(): string;

    /**
     * Returns the model name.
     *
     * @return string Model identifier (e.g., 'gpt-4o', 'claude-sonnet-4-20250514').
     */
    public function getModel(): string;

    /**
     * Returns the conversation messages.
     *
     * @return array<Message> Array of messages in chronological order.
     */
    public function getMessages(): array;

    /**
     * Returns provider-specific options that are passed directly to the payload (passthrough).
     *
     * @return array<string, mixed> Provider-specific options.
     */
    public function getOptions(): array;

    /**
     * Returns the maximum output token limit.
     *
     * @return int|null Token limit, or null for provider default.
     */
    public function getMaxTokens(): ?int;

    /**
     * Returns the temperature (creativity) setting.
     *
     * @return float|null Temperature value (0.0-2.0), or null for provider default.
     */
    public function getTemperature(): ?float;
}
