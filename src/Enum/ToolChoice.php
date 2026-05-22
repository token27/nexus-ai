<?php

declare(strict_types=1);

namespace Token27\NexusAI\Enum;

/**
 * Controls whether and how the LLM should use tools.
 *
 * Maps to provider-specific values:
 * - OpenAI:    Auto → "auto", Any → "required", None → "none"
 * - Anthropic: Auto → "auto", Any → "any",      None → "none"
 *
 * @see \Token27\NexusAI\Contract\ToolInterface
 */
enum ToolChoice: string
{
    /** The LLM decides whether to use tools or respond directly. Default. */
    case Auto = 'auto';

    /** The LLM MUST use at least one tool. */
    case Any = 'any';

    /** The LLM CANNOT use tools; it must respond with text only. */
    case None = 'none';
}
