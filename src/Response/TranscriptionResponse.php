<?php

declare(strict_types=1);

namespace Token27\NexusAI\Response;

use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Result of a Speech-to-Text (STT) transcription request.
 *
 * Contains the transcribed text and optional metadata like detected language,
 * audio duration, and timestamped segments (when verbose_json format is used).
 *
 * @see \Token27\NexusAI\Contract\AudioCapableInterface::transcribe()
 * @see \Token27\NexusAI\Request\TranscriptionRequest
 */
final readonly class TranscriptionResponse implements ResponseInterface
{
    /**
     * @param string $text Transcribed text from the audio.
     * @param string|null $language Detected language (ISO-639-1 code).
     * @param float|null $duration Duration of the audio in seconds.
     * @param array<mixed>|null $segments Timestamped segments (available with verbose_json format).
     * @param Usage $usage Token usage (if reported by the provider).
     * @param Meta $meta Response metadata (ID, model, rate limits).
     * @param array<string, mixed>|null $raw Raw JSON response from the provider.
     */
    public function __construct(
        public string $text,
        public ?string $language = null,
        public ?float $duration = null,
        public ?array $segments = null,
        public Usage $usage = new Usage(),
        public Meta $meta = new Meta(),
        public ?array $raw = null,
    ) {
    }

    /** {@inheritdoc} */
    public function getUsage(): Usage
    {
        return $this->usage;
    }

    /** {@inheritdoc} */
    public function getMeta(): Meta
    {
        return $this->meta;
    }

    /**
     * Transcription always completes successfully.
     *
     * @return FinishReason Always Stop.
     */
    public function getFinishReason(): FinishReason
    {
        return FinishReason::Stop;
    }

    /** {@inheritdoc} */
    public function getRaw(): ?array
    {
        return $this->raw;
    }
}
