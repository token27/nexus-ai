<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\Contract\UsageInterface;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Base contract shared by all response types (Text, Structured, Embedding).
 *
 * Provides the common properties that every response from an AI provider carries:
 * usage statistics, metadata, finish reason, and raw response data.
 *
 * This interface exists so that Pipeline\Context can store ANY type of response
 * without resorting to `mixed`.
 *
 * @see \Token27\NexusAI\Response\TextResponse
 * @see \Token27\NexusAI\Response\StructuredResponse
 * @see \Token27\NexusAI\Response\EmbeddingResponse
 */
interface ResponseInterface
{
    /**
     * Returns the token usage statistics for this request.
     *
     * @return UsageInterface The token usage data.
     */
    public function getUsage(): UsageInterface;

    /**
     * Returns response metadata: response ID, actual model used, rate limits.
     *
     * @return Meta The response metadata.
     */
    public function getMeta(): Meta;

    /**
     * Returns the reason why the LLM stopped generating.
     *
     * @return FinishReason The finish reason.
     */
    public function getFinishReason(): FinishReason;

    /**
     * Returns the raw JSON response from the provider for debugging.
     *
     * @return array<string, mixed>|null The raw response, or null if not stored.
     */
    public function getRaw(): ?array;
}
