<?php

declare(strict_types=1);

namespace Token27\NexusAI\Request;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Message\Message;

/**
 * Value Object containing all parameters for a Speech-to-Text (STT) transcription request.
 *
 * Supports OpenAI Whisper models and compatible APIs. The audioContent
 * should contain the raw audio bytes (read from a file or received from a stream).
 *
 * @see \Token27\NexusAI\Contract\AudioCapableInterface::transcribe()
 * @see \Token27\NexusAI\Response\TranscriptionResponse
 */
final readonly class TranscriptionRequest implements RequestInterface
{
    /**
     * @param string $provider Provider key (e.g., 'openai').
     * @param string $model Model identifier (e.g., 'whisper-1').
     * @param string $audioContent Raw audio bytes or base64-encoded audio data.
     * @param string $audioFilename Filename for the audio (e.g., 'audio.mp3'). Used as form-data filename.
     * @param string|null $language ISO-639-1 language code (e.g., 'en', 'es'). Null for auto-detection.
     * @param string $responseFormat Response format: 'json', 'text', 'verbose_json'. Default 'json'.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $audioContent,
        public string $audioFilename,
        public ?string $language = null,
        public string $responseFormat = 'json',
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
     * Transcription requests don't use conversation messages.
     *
     * @return array<Message> Always empty.
     */
    public function getMessages(): array
    {
        return [];
    }

    /**
     * Transcription requests don't use passthrough options.
     *
     * @return array<string, mixed> Always empty.
     */
    public function getOptions(): array
    {
        return [];
    }

    /**
     * Transcription requests don't use max tokens.
     *
     * @return int|null Always null.
     */
    public function getMaxTokens(): ?int
    {
        return null;
    }

    /**
     * Transcription requests don't use temperature.
     *
     * @return float|null Always null.
     */
    public function getTemperature(): ?float
    {
        return null;
    }
}
