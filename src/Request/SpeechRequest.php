<?php

declare(strict_types=1);

namespace Token27\NexusAI\Request;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Message\Message;

/**
 * Value Object containing all parameters for a Text-to-Speech (TTS) request.
 *
 * Supports OpenAI TTS models (tts-1, tts-1-hd) and compatible APIs.
 * The driver is responsible for mapping these parameters to the provider-specific payload.
 *
 * @see \Token27\NexusAI\Contract\AudioCapableInterface::speak()
 * @see \Token27\NexusAI\Response\SpeechResponse
 */
final readonly class SpeechRequest implements RequestInterface
{
    /**
     * @param string $provider Provider key (e.g., 'openai').
     * @param string $model Model identifier (e.g., 'tts-1', 'tts-1-hd').
     * @param string $input Text to convert to audio.
     * @param string $voice Voice to use: 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'.
     * @param string $responseFormat Audio format: 'mp3', 'opus', 'aac', 'flac'. Default 'mp3'.
     * @param float $speed Speed multiplier: 0.25 to 4.0. Default 1.0.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $input,
        public string $voice,
        public string $responseFormat = 'mp3',
        public float $speed = 1.0,
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

    /**
     * Speech requests don't use conversation messages.
     *
     * @return array<Message> Always empty.
     */
    public function getMessages(): array
    {
        return [];
    }

    /**
     * Speech requests don't use passthrough options.
     *
     * @return array<string, mixed> Always empty.
     */
    public function getOptions(): array
    {
        return [];
    }

    /**
     * Speech requests don't use max tokens.
     *
     * @return int|null Always null.
     */
    public function getMaxTokens(): ?int
    {
        return null;
    }

    /**
     * Speech requests don't use temperature.
     *
     * @return float|null Always null.
     */
    public function getTemperature(): ?float
    {
        return null;
    }
}
