<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Request\SpeechRequest;
use Token27\NexusAI\Request\TranscriptionRequest;
use Token27\NexusAI\Response\SpeechResponse;
use Token27\NexusAI\Response\TranscriptionResponse;

/**
 * Optional interface for drivers that support Text-to-Speech (TTS) and Speech-to-Text (STT).
 *
 * Separated from the main DriverInterface so that providers without
 * audio capabilities don't need to declare unsupported methods.
 * Use `$driver instanceof AudioCapableInterface` or
 * `$driver->supports(DriverInterface::CAPABILITY_AUDIO)` to check.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface
 * @see \Token27\NexusAI\Request\SpeechRequest
 * @see \Token27\NexusAI\Request\TranscriptionRequest
 */
interface AudioCapableInterface
{
    /**
     * Text-to-Speech: converts text to audio.
     *
     * @param SpeechRequest $request The TTS request with text and voice parameters.
     * @return SpeechResponse The generated audio response.
     */
    public function speak(SpeechRequest $request): SpeechResponse;

    /**
     * Speech-to-Text: converts audio to text.
     *
     * @param TranscriptionRequest $request The STT request with audio content.
     * @return TranscriptionResponse The transcription response.
     */
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse;
}
