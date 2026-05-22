<?php

declare(strict_types=1);

namespace Token27\NexusAI\Response;

use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Result of a Text-to-Speech (TTS) request.
 *
 * Contains the raw audio bytes and the MIME type of the generated audio.
 * The audioContent can be written directly to a file or streamed to the client.
 *
 * @see \Token27\NexusAI\Contract\AudioCapableInterface::speak()
 * @see \Token27\NexusAI\Request\SpeechRequest
 */
final readonly class SpeechResponse implements ResponseInterface
{
    /**
     * @param string $audioContent Raw audio bytes.
     * @param string $format MIME type of the audio (e.g., 'audio/mpeg', 'audio/opus', 'audio/aac', 'audio/flac').
     * @param Usage $usage Token usage (if reported by the provider).
     * @param Meta $meta Response metadata (ID, model, rate limits).
     * @param array<string, mixed>|null $raw Raw response data for debugging. Null for binary responses.
     */
    public function __construct(
        public string $audioContent,
        public string $format,
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
     * TTS always completes successfully.
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
