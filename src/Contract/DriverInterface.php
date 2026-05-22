<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Generator;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;

/**
 * Central contract that EVERY AI provider implementation must fulfill.
 *
 * This is the core interface of NexusAI. Each driver (OpenAI, Anthropic, Gemini, etc.)
 * implements this interface to provide a unified API for AI interactions.
 *
 * Unlike Prism which forces ALL providers to declare unsupported methods (throwing exceptions),
 * NexusAI keeps only universal operations here. Optional capabilities (images, audio)
 * are separate interfaces (ImageCapableInterface, AudioCapableInterface).
 *
 * The supports() method provides runtime introspection of driver capabilities.
 *
 * @see \Token27\NexusAI\Driver\AbstractDriver
 */
interface DriverInterface
{
    /** @var string Capability: standard chat completion. */
    public const string CAPABILITY_TEXT = 'text';

    /** @var string Capability: structured (JSON schema) output. */
    public const string CAPABILITY_STRUCTURED = 'structured';

    /** @var string Capability: text-to-vector embeddings. */
    public const string CAPABILITY_EMBEDDINGS = 'embeddings';

    /** @var string Capability: streaming responses via Generator. */
    public const string CAPABILITY_STREAMING = 'streaming';

    /** @var string Capability: tool/function calling. */
    public const string CAPABILITY_TOOLS = 'tools';

    /** @var string Capability: image generation. */
    public const string CAPABILITY_IMAGES = 'images';

    /** @var string Capability: audio (TTS/STT). */
    public const string CAPABILITY_AUDIO = 'audio';

    /**
     * Standard chat completion. Sends messages to the LLM and receives text.
     *
     * @param TextRequest $request The text request with messages and parameters.
     * @return TextResponse The completion response.
     */
    public function text(TextRequest $request): TextResponse;

    /**
     * Structured output. Like text() but forces JSON conforming to a schema.
     *
     * @param StructuredRequest $request The structured request with schema definition.
     * @return StructuredResponse The structured response with parsed data.
     */
    public function structured(StructuredRequest $request): StructuredResponse;

    /**
     * Embeddings. Converts text(s) into numerical vectors.
     *
     * @param EmbeddingRequest $request The embedding request with input texts.
     * @return EmbeddingResponse The embedding vectors.
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse;

    /**
     * Streaming via PHP Generator. Each yield emits a chunk as it arrives.
     *
     * @param TextRequest $request The text request to stream.
     * @return Generator<int, StreamChunkInterface> Generator yielding stream chunks.
     */
    public function stream(TextRequest $request): Generator;

    /**
     * Runtime capability introspection.
     *
     * @param string $capability One of the CAPABILITY_* constants.
     * @return bool Whether this driver supports the given capability.
     */
    public function supports(string $capability): bool;
}
