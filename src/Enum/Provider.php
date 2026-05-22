<?php

declare(strict_types=1);

namespace Token27\NexusAI\Enum;

/**
 * Identifiers for supported AI providers.
 *
 * Used for auto-discovery of drivers and configuration. Each case defines
 * a provider key and its default base URL. The user can override the base URL
 * via configuration.
 *
 * Unlike Prism which ties each enum case to a Laravel container factory,
 * NexusAI only defines keys here — driver resolution is handled by DriverRegistry.
 *
 * @see \Token27\NexusAI\Driver\DriverRegistry
 */
enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Ollama = 'ollama';
    case DeepSeek = 'deepseek';
    case Mistral = 'mistral';
    case Groq = 'groq';
    case XAI = 'xai';
    case Perplexity = 'perplexity';

    /**
     * Returns the default base URL for this provider.
     *
     * The user can override this in the driver configuration.
     *
     * @return string The default API base URL.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::OpenAI => 'https://api.openai.com/v1',
            self::Anthropic => 'https://api.anthropic.com/v1',
            self::Gemini => 'https://generativelanguage.googleapis.com/v1beta',
            self::Ollama => 'http://localhost:11434/v1',
            self::DeepSeek => 'https://api.deepseek.com/v1',
            self::Mistral => 'https://api.mistral.ai/v1',
            self::Groq => 'https://api.groq.com/openai/v1',
            self::XAI => 'https://api.x.ai/v1',
            self::Perplexity => 'https://api.perplexity.ai',
        };
    }
}
