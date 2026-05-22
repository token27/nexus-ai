<?php

declare(strict_types=1);

namespace Token27\NexusAI;

use Generator;
use Token27\NexusAI\Contract\AudioCapableInterface;
use Token27\NexusAI\Contract\ImageCapableInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Contract\ToolInterface;
use Token27\NexusAI\Driver\DriverRegistry;
use Token27\NexusAI\Enum\StructuredMode;
use Token27\NexusAI\Enum\ToolChoice;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Pipeline;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\ImageRequest;
use Token27\NexusAI\Request\SpeechRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Request\TranscriptionRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\ImageResponse;
use Token27\NexusAI\Response\SpeechResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\Response\TranscriptionResponse;

/**
 * Fluent builder that accumulates request options and executes the request.
 *
 * This is the primary class users interact with. Methods are chained to
 * configure the request, then a terminal method (asText, asStructured,
 * asEmbeddings, asStream, asImage, asSpeech, asTranscription) executes
 * it through the pipeline and driver.
 *
 * Unlike Prism's 15+ traits, NexusAI uses a single mutable builder class
 * for simplicity and debuggability.
 *
 * Usage:
 *   $response = $pendingRequest
 *       ->withSystemPrompt('Be helpful')
 *       ->withPrompt('Hello')
 *       ->withTemperature(0.7)
 *       ->asText();
 *
 * @see \Token27\NexusAI\NexusAI
 * @see \Token27\NexusAI\Pipeline\Pipeline
 */
final class PendingRequest
{
    // ──────────────────────────────────────────────────────────────────
    //  Core / Text Properties
    // ──────────────────────────────────────────────────────────────────

    /** @var string Provider key set by using(). */
    private string $provider;

    /** @var string Model identifier set by using(). */
    private string $model;

    /** @var string|null System prompt. */
    private ?string $systemPrompt = null;

    /** @var string|null User prompt shortcut. */
    private ?string $prompt = null;

    /** @var array<Message> Conversation messages. */
    private array $messages = [];

    /** @var int|null Maximum output tokens. */
    private ?int $maxTokens = null;

    /** @var float|null Temperature (0.0-2.0). */
    private ?float $temperature = null;

    /** @var float|null Top-p (nucleus sampling). */
    private ?float $topP = null;

    /** @var array<ToolInterface> Registered tools. */
    private array $tools = [];

    /** @var ToolChoice|null Tool choice strategy. */
    private ?ToolChoice $toolChoice = null;

    /** @var int Maximum tool-call→result cycles. */
    private int $maxSteps = 1;

    /** @var array<string, mixed> Provider-specific passthrough options. */
    private array $options = [];

    /** @var PricingEngineInterface|null Per-request pricing engine override. */
    private ?PricingEngineInterface $pricingEngine = null;

    // ──────────────────────────────────────────────────────────────────
    //  Image Generation Properties
    // ──────────────────────────────────────────────────────────────────

    /** @var string|null Image dimensions (e.g., '1024x1024', '1792x1024'). */
    private ?string $size = null;

    /** @var string|null Image quality: 'standard' or 'hd'. */
    private ?string $quality = null;

    /** @var int Number of images to generate. */
    private int $numberOfImages = 1;

    /** @var string|null Output format: 'png', 'jpeg', 'webp'. */
    private ?string $outputFormat = null;

    /** @var int|null Output compression (0-100) for jpeg/webp. */
    private ?int $outputCompression = null;

    /** @var string|null Image background: 'transparent', 'opaque', 'auto'. */
    private ?string $background = null;

    /** @var string|null Moderation level: 'low' or 'auto'. */
    private ?string $moderation = null;

    /** @var string|null Resolution for xAI providers: '1k' or '2k'. */
    private ?string $resolution = null;

    // ──────────────────────────────────────────────────────────────────
    //  Text-to-Speech (TTS) Properties
    // ──────────────────────────────────────────────────────────────────

    /** @var string|null TTS voice: 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'. */
    private ?string $voice = null;

    /** @var string TTS audio format: 'mp3', 'opus', 'aac', 'flac'. */
    private string $audioFormat = 'mp3';

    /** @var float TTS speed multiplier (0.25 to 4.0). */
    private float $speed = 1.0;

    // ──────────────────────────────────────────────────────────────────
    //  Speech-to-Text (STT) Properties
    // ──────────────────────────────────────────────────────────────────

    /** @var string|null Raw audio bytes or base64-encoded audio for transcription. */
    private ?string $audioContent = null;

    /** @var string|null Audio filename for transcription (e.g., 'audio.mp3'). */
    private ?string $audioFilename = null;

    /** @var string|null ISO-639-1 language hint for transcription (null = auto-detect). */
    private ?string $language = null;

    /** @var string Transcription response format: 'json', 'text', 'verbose_json'. */
    private string $transcriptionResponseFormat = 'json';

    /**
     * @param DriverRegistry $registry Registry to resolve driver instances.
     * @param Pipeline $pipeline Pipeline with middleware stack.
     */
    public function __construct(
        private readonly DriverRegistry $registry,
        private readonly Pipeline $pipeline,
        string $provider,
        string $model,
    ) {
        $this->provider = $provider;
        $this->model = $model;
    }

    // ══════════════════════════════════════════════════════════════════
    //  FLUENT SETTERS — Core / Text
    // ══════════════════════════════════════════════════════════════════

    /**
     * Sets the system prompt for the request.
     *
     * @param string $prompt The system instruction text.
     * @return $this
     */
    public function withSystemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * Sets the user prompt, creating a UserMessage automatically.
     *
     * Shortcut for withMessages([new UserMessage($prompt)]).
     *
     * @param string $prompt The user message text.
     * @return $this
     */
    public function withPrompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Sets the conversation messages directly.
     *
     * Use for multi-turn conversations where you manage the message history.
     *
     * @param array<Message> $messages The conversation messages.
     * @return $this
     */
    public function withMessages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Sets the maximum output token limit.
     *
     * @param int $max Maximum tokens to generate.
     * @return $this
     */
    public function withMaxTokens(int $max): self
    {
        $this->maxTokens = $max;

        return $this;
    }

    /**
     * Sets the temperature (creativity) parameter.
     *
     * @param float $temp Temperature value (0.0-2.0).
     * @return $this
     */
    public function withTemperature(float $temp): self
    {
        $this->temperature = $temp;

        return $this;
    }

    /**
     * Sets the top-p (nucleus sampling) parameter.
     *
     * @param float $p Top-p value (0.0-1.0).
     * @return $this
     */
    public function withTopP(float $p): self
    {
        $this->topP = $p;

        return $this;
    }

    /**
     * Sets the tools available for the LLM to call.
     *
     * @param array<ToolInterface> $tools Tool definitions.
     * @return $this
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Sets the tool choice strategy.
     *
     * @param ToolChoice $choice How the LLM should use tools.
     * @return $this
     */
    public function withToolChoice(ToolChoice $choice): self
    {
        $this->toolChoice = $choice;

        return $this;
    }

    /**
     * Sets the maximum tool-call→result cycles for tool loops.
     *
     * @param int $steps Maximum steps (1 = no tool loop).
     * @return $this
     */
    public function withMaxSteps(int $steps): self
    {
        $this->maxSteps = $steps;

        return $this;
    }

    /**
     * Adds a provider-specific passthrough option.
     *
     * These are merged directly into the API payload for options
     * not covered by the standard parameters.
     *
     * @param string $key Option key (e.g., 'presence_penalty').
     * @param mixed $value Option value.
     * @return $this
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Overrides the pricing engine for this request only.
     *
     * Useful when a specific request requires custom prices (e.g., negotiated enterprise
     * rates, internal cost models, or testing with mock prices) while the global pipeline
     * uses the default engine.
     *
     * @param PricingEngineInterface $engine The engine to use for this request.
     * @return $this
     */
    public function withPricingEngine(PricingEngineInterface $engine): self
    {
        $this->pricingEngine = $engine;

        return $this;
    }

    // ══════════════════════════════════════════════════════════════════
    //  FLUENT SETTERS — Image Generation
    // ══════════════════════════════════════════════════════════════════

    /**
     * Sets the image dimensions for generation.
     *
     * Common sizes: '256x256', '512x512', '1024x1024', '1792x1024', '1024x1792'.
     * Supported sizes vary by provider and model.
     *
     * @param string $size Image dimensions string (e.g., '1024x1024').
     * @return $this
     */
    public function withSize(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Sets the image quality level.
     *
     * @param string $quality 'standard' or 'hd'.
     * @return $this
     */
    public function withQuality(string $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * Sets the number of images to generate.
     *
     * @param int $n Number of images (1-10, varies by provider).
     * @return $this
     */
    public function withNumberOfImages(int $n): self
    {
        $this->numberOfImages = $n;

        return $this;
    }

    public function withOutputFormat(string $format): self
    {
        $this->outputFormat = $format;

        return $this;
    }

    public function withOutputCompression(int $percentage): self
    {
        $this->outputCompression = $percentage;

        return $this;
    }

    public function withBackground(string $background): self
    {
        $this->background = $background;

        return $this;
    }

    public function withModeration(string $level): self
    {
        $this->moderation = $level;

        return $this;
    }

    public function withResolution(string $resolution): self
    {
        $this->resolution = $resolution;

        return $this;
    }

    // ══════════════════════════════════════════════════════════════════
    //  FLUENT SETTERS — Text-to-Speech (TTS)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Sets the voice for TTS audio generation.
     *
     * Common OpenAI voices: 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'.
     * Available voices vary by provider.
     *
     * @param string $voice Voice identifier.
     * @return $this
     */
    public function withVoice(string $voice): self
    {
        $this->voice = $voice;

        return $this;
    }

    /**
     * Sets the output audio format for TTS.
     *
     * @param string $format Audio format: 'mp3', 'opus', 'aac', 'flac'.
     * @return $this
     */
    public function withAudioFormat(string $format): self
    {
        $this->audioFormat = $format;

        return $this;
    }

    /**
     * Sets the playback speed for TTS audio.
     *
     * @param float $speed Speed multiplier (0.25 to 4.0). Default 1.0.
     * @return $this
     */
    public function withSpeed(float $speed): self
    {
        $this->speed = $speed;

        return $this;
    }

    // ══════════════════════════════════════════════════════════════════
    //  FLUENT SETTERS — Speech-to-Text (STT / Transcription)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Sets the audio content for transcription.
     *
     * Accepts raw audio bytes (from file_get_contents) or base64-encoded audio data.
     * The filename is used as the form-data filename in the multipart upload.
     *
     * @param string $content Raw audio bytes or base64-encoded audio.
     * @param string $filename Filename for the audio (e.g., 'recording.mp3').
     * @return $this
     */
    public function withAudioContent(string $content, string $filename): self
    {
        $this->audioContent = $content;
        $this->audioFilename = $filename;

        return $this;
    }

    /**
     * Sets the language hint for transcription.
     *
     * Providing the language improves accuracy and latency.
     * Use ISO-639-1 codes (e.g., 'en', 'es', 'fr', 'de', 'ja').
     *
     * @param string $language ISO-639-1 language code.
     * @return $this
     */
    public function withLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Sets the response format for transcription output.
     *
     * @param string $format 'json', 'text', or 'verbose_json' (includes timestamps).
     * @return $this
     */
    public function withTranscriptionResponseFormat(string $format): self
    {
        $this->transcriptionResponseFormat = $format;

        return $this;
    }

    // ══════════════════════════════════════════════════════════════════
    //  TERMINAL METHODS — Text / Structured / Embeddings / Stream
    // ══════════════════════════════════════════════════════════════════

    /**
     * Executes the request as a text (chat completion).
     *
     * Flow:
     * 1. Build TextRequest from accumulated state
     * 2. Create Context from the request
     * 3. Resolve the driver from the registry
     * 4. Execute through the middleware pipeline
     * 5. Return the TextResponse
     *
     * @return TextResponse The completion response.
     */
    public function asText(): TextResponse
    {
        $request = $this->buildTextRequest();
        $driver = $this->registry->resolve($this->provider);

        $context = Context::create($request);
        if ($this->pricingEngine !== null) {
            $context = $context->withMeta('_pricing_engine', $this->pricingEngine);
        }

        $context = $this->pipeline->send($context, function (Context $ctx) use ($driver): Context {
            /** @var TextRequest $req */
            $req = $ctx->getRequest();
            $response = $driver->text($req);

            return $ctx->withResponse($response)->withUsage($response->usage);
        });

        /** @var TextResponse $response */
        $response = $context->getResponse();

        return $this->attachPricingToTextResponse($response, $context->getPricingResult());
    }

    /**
     * Executes the request as structured output.
     *
     * @param string $class FQCN of the target DTO class.
     * @param StructuredMode|null $mode Strategy for enforcing JSON output.
     * @return StructuredResponse The structured response with the parsed DTO.
     */
    public function asStructured(string $class, ?StructuredMode $mode = null): StructuredResponse
    {
        $request = $this->buildStructuredRequest($class, $mode);
        $driver = $this->registry->resolve($this->provider);

        $context = Context::create($request);
        if ($this->pricingEngine !== null) {
            $context = $context->withMeta('_pricing_engine', $this->pricingEngine);
        }

        $context = $this->pipeline->send($context, function (Context $ctx) use ($driver): Context {
            /** @var StructuredRequest $req */
            $req = $ctx->getRequest();
            $response = $driver->structured($req);

            return $ctx->withResponse($response)->withUsage($response->usage);
        });

        /** @var StructuredResponse */
        return $context->getResponse();
    }

    /**
     * Executes the request as an embedding.
     *
     * @return EmbeddingResponse The embedding response.
     *
     * @throws \LogicException If no prompt is set (embeddings need input text).
     */
    public function asEmbeddings(): EmbeddingResponse
    {
        if ($this->prompt === null) {
            throw new \LogicException(
                'Embedding requests require a prompt. Call withPrompt() first.',
            );
        }

        $request = new EmbeddingRequest(
            provider: $this->provider,
            model: $this->model,
            input: $this->prompt,
            options: $this->options,
        );

        $driver = $this->registry->resolve($this->provider);

        $context = Context::create($request);
        if ($this->pricingEngine !== null) {
            $context = $context->withMeta('_pricing_engine', $this->pricingEngine);
        }

        $context = $this->pipeline->send($context, function (Context $ctx) use ($driver): Context {
            /** @var EmbeddingRequest $req */
            $req = $ctx->getRequest();
            $response = $driver->embeddings($req);

            return $ctx->withResponse($response)->withUsage($response->usage);
        });

        /** @var EmbeddingResponse */
        return $context->getResponse();
    }

    /**
     * Executes the request as a streaming response.
     *
     * Runs the middleware pipeline before streaming so that middlewares
     * (rate limiting, budget checking, circuit breaker) can inspect
     * and potentially short-circuit the request before it starts.
     *
     * The stream itself runs outside the pipeline since it's a Generator.
     *
     * @return Generator<int, StreamChunkInterface> Generator yielding stream chunks.
     */
    public function asStream(): Generator
    {
        $request = $this->buildTextRequest();
        $driver = $this->registry->resolve($this->provider);

        $context = $this->pipeline->send(
            Context::create($request),
            function (Context $ctx): Context {
                // Mark this as a stream request so middlewares can inspect
                return $ctx->withMeta('_streaming', true);
            },
        );

        yield from $driver->stream($request);
    }

    // ══════════════════════════════════════════════════════════════════
    //  TERMINAL METHODS — Image / Speech / Transcription
    // ══════════════════════════════════════════════════════════════════

    /**
     * Executes the request as image generation.
     *
     * Flow:
     * 1. Build ImageRequest from accumulated state
     * 2. Verify driver implements ImageCapableInterface
     * 3. Create Context and execute through the middleware pipeline
     * 4. Return the ImageResponse with generated image(s)
     *
     * @return ImageResponse The generated image(s) response.
     *
     * @throws \LogicException If no prompt is set or the driver doesn't support image generation.
     */
    public function asImage(): ImageResponse
    {
        $request = $this->buildImageRequest();
        $driver = $this->registry->resolve($this->provider);

        if (!$driver instanceof ImageCapableInterface) {
            throw new \LogicException("Driver [{$this->provider}] does not support Image Generation.");
        }

        $context = Context::create($request);
        if ($this->pricingEngine !== null) {
            $context = $context->withMeta('_pricing_engine', $this->pricingEngine);
        }

        $context = $this->pipeline->send($context, function (Context $ctx) use ($driver): Context {
            /** @var ImageRequest $req */
            $req = $ctx->getRequest();
            $response = $driver->image($req);

            return $ctx->withResponse($response)->withUsage($response->usage);
        });

        /** @var ImageResponse $response */
        $response = $context->getResponse();

        return $this->attachPricingToImageResponse($response, $context->getPricingResult());
    }

    /**
     * Executes the request as Text-to-Speech (TTS).
     *
     * Converts the text set via withPrompt() into audio using the specified
     * voice (withVoice()). The resulting SpeechResponse contains raw audio
     * bytes that can be saved to a file or streamed.
     *
     * Flow:
     * 1. Build SpeechRequest from accumulated state
     * 2. Verify driver implements AudioCapableInterface
     * 3. Create Context and execute through the middleware pipeline
     * 4. Return the SpeechResponse with audio content
     *
     * @return SpeechResponse The generated audio response.
     *
     * @throws \LogicException If prompt or voice is missing, or driver doesn't support audio.
     */
    public function asSpeech(): SpeechResponse
    {
        $request = $this->buildSpeechRequest();
        $driver = $this->registry->resolve($this->provider);

        if (!$driver instanceof AudioCapableInterface) {
            throw new \LogicException("Driver [{$this->provider}] does not support Text-to-Speech.");
        }

        $context = Context::create($request);
        if ($this->pricingEngine !== null) {
            $context = $context->withMeta('_pricing_engine', $this->pricingEngine);
        }

        $context = $this->pipeline->send($context, function (Context $ctx) use ($driver): Context {
            /** @var SpeechRequest $req */
            $req = $ctx->getRequest();
            $response = $driver->speak($req);

            return $ctx->withResponse($response)->withUsage($response->usage);
        });

        /** @var SpeechResponse */
        return $context->getResponse();
    }

    /**
     * Executes the request as Speech-to-Text (STT) transcription.
     *
     * Transcribes the audio content set via withAudioContent() into text.
     * Optionally provide a language hint with withLanguage() for improved
     * accuracy and latency.
     *
     * Flow:
     * 1. Build TranscriptionRequest from accumulated state
     * 2. Verify driver implements AudioCapableInterface
     * 3. Create Context and execute through the middleware pipeline
     * 4. Return the TranscriptionResponse with transcribed text
     *
     * @return TranscriptionResponse The transcription response.
     *
     * @throws \LogicException If audio content is missing, or driver doesn't support audio.
     */
    public function asTranscription(): TranscriptionResponse
    {
        $request = $this->buildTranscriptionRequest();
        $driver = $this->registry->resolve($this->provider);

        if (!$driver instanceof AudioCapableInterface) {
            throw new \LogicException("Driver [{$this->provider}] does not support Audio Transcription.");
        }

        $context = Context::create($request);
        if ($this->pricingEngine !== null) {
            $context = $context->withMeta('_pricing_engine', $this->pricingEngine);
        }

        $context = $this->pipeline->send($context, function (Context $ctx) use ($driver): Context {
            /** @var TranscriptionRequest $req */
            $req = $ctx->getRequest();
            $response = $driver->transcribe($req);

            return $ctx->withResponse($response)->withUsage($response->usage);
        });

        /** @var TranscriptionResponse */
        return $context->getResponse();
    }

    // ══════════════════════════════════════════════════════════════════
    //  PRIVATE BUILDERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Builds a TextRequest from the accumulated builder state.
     *
     * @return TextRequest The constructed request.
     */
    private function buildTextRequest(): TextRequest
    {
        $messages = $this->messages;

        // If a prompt was set, add it as a UserMessage
        if ($this->prompt !== null) {
            $messages[] = new UserMessage($this->prompt);
        }

        return new TextRequest(
            provider: $this->provider,
            model: $this->model,
            messages: $messages,
            systemPrompt: $this->systemPrompt,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            topP: $this->topP,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            maxSteps: $this->maxSteps,
            options: $this->options,
        );
    }

    /**
     * Builds a StructuredRequest from the accumulated builder state.
     *
     * @param string $class FQCN of the target DTO.
     * @param StructuredMode|null $mode Output enforcement strategy.
     * @return StructuredRequest The constructed request.
     */
    private function buildStructuredRequest(string $class, ?StructuredMode $mode = null): StructuredRequest
    {
        $messages = $this->messages;

        if ($this->prompt !== null) {
            $messages[] = new UserMessage($this->prompt);
        }

        return new StructuredRequest(
            provider: $this->provider,
            model: $this->model,
            outputClass: $class,
            messages: $messages,
            systemPrompt: $this->systemPrompt,
            mode: $mode ?? StructuredMode::Auto,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            topP: $this->topP,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            maxSteps: $this->maxSteps,
            options: $this->options,
        );
    }

    /**
     * Builds an ImageRequest from the accumulated builder state.
     *
     * @return ImageRequest The constructed image generation request.
     *
     * @throws \LogicException If no prompt has been set.
     */
    private function buildImageRequest(): ImageRequest
    {
        if ($this->prompt === null) {
            throw new \LogicException(
                'Image generation requires a prompt. Call withPrompt() first.',
            );
        }

        return new ImageRequest(
            provider: $this->provider,
            model: $this->model,
            prompt: $this->prompt,
            size: $this->size,
            quality: $this->quality,
            outputFormat: $this->outputFormat,
            outputCompression: $this->outputCompression,
            background: $this->background,
            moderation: $this->moderation,
            n: $this->numberOfImages,
            resolution: $this->resolution,
            options: $this->options,
        );
    }

    /**
     * Builds a SpeechRequest from the accumulated builder state.
     *
     * @return SpeechRequest The constructed TTS request.
     *
     * @throws \LogicException If no prompt or voice has been set.
     */
    private function buildSpeechRequest(): SpeechRequest
    {
        if ($this->prompt === null) {
            throw new \LogicException(
                'Text-to-Speech requires input text. Call withPrompt() first.',
            );
        }

        if ($this->voice === null) {
            throw new \LogicException(
                'Text-to-Speech requires a voice. Call withVoice() first.',
            );
        }

        return new SpeechRequest(
            provider: $this->provider,
            model: $this->model,
            input: $this->prompt,
            voice: $this->voice,
            responseFormat: $this->audioFormat,
            speed: $this->speed,
        );
    }

    /**
     * Builds a TranscriptionRequest from the accumulated builder state.
     *
     * @return TranscriptionRequest The constructed STT request.
     *
     * @throws \LogicException If no audio content or filename has been set.
     */
    private function buildTranscriptionRequest(): TranscriptionRequest
    {
        if ($this->audioContent === null || $this->audioFilename === null) {
            throw new \LogicException(
                'Transcription requires audio content. Call withAudioContent($bytes, $filename) first.',
            );
        }

        return new TranscriptionRequest(
            provider: $this->provider,
            model: $this->model,
            audioContent: $this->audioContent,
            audioFilename: $this->audioFilename,
            language: $this->language,
            responseFormat: $this->transcriptionResponseFormat,
        );
    }

    private function attachPricingToTextResponse(
        TextResponse $response,
        ?PricingResultInterface $pricingResult,
    ): TextResponse {
        if ($pricingResult === null) {
            return $response;
        }

        return $response->withPricingResult($pricingResult);
    }

    private function attachPricingToImageResponse(
        ImageResponse $response,
        ?PricingResultInterface $pricingResult,
    ): ImageResponse {
        if ($pricingResult === null) {
            return $response;
        }

        return $response->withPricingResult($pricingResult);
    }
}
