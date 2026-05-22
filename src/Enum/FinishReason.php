<?php

declare(strict_types=1);

namespace Token27\NexusAI\Enum;

/**
 * Reason why the LLM stopped generating text.
 *
 * Normalizes the different finish reasons across providers into a unified enum.
 * Each provider has its own naming convention; the driver's ResponseParser
 * is responsible for mapping provider-specific values to these cases.
 *
 * Provider mapping reference:
 * - OpenAI:    stop → Stop, length → Length, content_filter → ContentFilter, tool_calls → ToolCalls
 * - Anthropic: end_turn → Stop, max_tokens → Length, tool_use → ToolCalls
 * - Gemini:    STOP → Stop, MAX_TOKENS → Length, SAFETY → ContentFilter
 *
 * @see \Token27\NexusAI\Contract\ResponseInterface
 */
enum FinishReason: string
{
    /** The LLM finished naturally (generated end-of-sequence token). */
    case Stop = 'stop';

    /** The max_tokens limit was reached. The response may be truncated. */
    case Length = 'length';

    /** Content was blocked by the provider's safety filters. */
    case ContentFilter = 'content_filter';

    /** The LLM wants to execute tools before continuing. */
    case ToolCalls = 'tool_calls';

    /** An error occurred during generation. */
    case Error = 'error';

    /** A known reason that is not mapped to a specific case. */
    case Other = 'other';

    /** The provider did not send a finish_reason. */
    case Unknown = 'unknown';
}
