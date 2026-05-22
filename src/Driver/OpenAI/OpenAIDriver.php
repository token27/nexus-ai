<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\OpenAI;

use DateTimeImmutable;
use Generator;
use Token27\NexusAI\Contract\AudioCapableInterface;
use Token27\NexusAI\Contract\ImageCapableInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Driver\AbstractDriver;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\ToolCallMessage;
use Token27\NexusAI\Message\ToolResultMessage;
use Token27\NexusAI\Pricing\ValueObject\Usage;
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
use Token27\NexusAI\Tool\ToolExecutor;
use Token27\NexusAI\Tool\ToolRegistry;
use Token27\NexusAI\ValueObject\GeneratedImage;
use Token27\NexusAI\ValueObject\Meta;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Concrete driver for OpenAI and OpenAI-compatible APIs.
 *
 * Supports OpenAI, Azure OpenAI, Groq, DeepSeek, and any API implementing
 * the OpenAI chat completions protocol. The base URL determines the target.
 *
 * Also implements optional ImageCapableInterface (DALL-E) and AudioCapableInterface
 * (TTS via tts-1/tts-1-hd, STT via whisper-1) for full OpenAI multimodal support.
 *
 * Decomposed into three stateless collaborators:
 * - OpenAIPayloadBuilder: NexusAI objects → OpenAI JSON payload
 * - OpenAIResponseParser: OpenAI JSON response → NexusAI value objects
 * - OpenAIStreamParser: SSE stream → Generator<StreamChunkInterface>
 *
 * @see \Token27\NexusAI\Driver\AbstractDriver
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIPayloadBuilder
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIResponseParser
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIStreamParser
 */
final class OpenAIDriver extends AbstractDriver implements ImageCapableInterface, AudioCapableInterface
{
    /**
     * {@inheritdoc}
     *
     * Sends a chat completion request to OpenAI with tool loop support.
     *
     * Flow:
     * 1. Build payload via OpenAIPayloadBuilder
     * 2. POST to /chat/completions
     * 3. Parse response via OpenAIResponseParser
     * 4. If FinishReason::ToolCalls and step < maxSteps → execute tools, loop
     * 5. Return TextResponse with accumulated steps
     */
    public function text(TextRequest $request): TextResponse
    {
        /** @var array<Message> $messages */
        $messages = $request->resolveMessages();
        /** @var array<TextResponse> $steps */
        $steps = [];
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;
        $step = 0;

        // Create tool executor if tools are present
        $executor = null;
        if ($request->tools !== []) {
            $registry = new ToolRegistry();
            $registry->registerMany($request->tools);
            $executor = new ToolExecutor($registry);
        }

        while (true) {
            // Build a request with the current messages (system prompt only on first iteration)
            $currentRequest = new TextRequest(
                provider: $request->provider,
                model: $request->model,
                messages: $step === 0 ? $messages : $messages,
                systemPrompt: $step === 0 ? $request->systemPrompt : null,
                maxTokens: $request->maxTokens,
                temperature: $request->temperature,
                topP: $request->topP,
                tools: $request->tools,
                toolChoice: $request->toolChoice,
                maxSteps: $request->maxSteps,
                options: $request->options,
            );

            $payload = OpenAIPayloadBuilder::buildTextPayload($currentRequest);

            // On subsequent iterations, replace messages with the full accumulated history
            if ($step > 0) {
                $payload['messages'] = OpenAIPayloadBuilder::mapMessages($messages);
            }

            $result = $this->sendRequest('POST', '/chat/completions', $payload);
            $response = OpenAIResponseParser::parseTextResponse($result['data'], $result['rateLimits']);

            // Accumulate usage
            $totalPromptTokens += $response->usage->textInputTokens;
            $totalCompletionTokens += $response->usage->textOutputTokens;

            $step++;

            // If not tool calls, no executor, or maxSteps reached → return final response
            if (
                $response->finishReason !== FinishReason::ToolCalls
                || $executor === null
                || $step >= $request->maxSteps
            ) {
                return new TextResponse(
                    text: $response->text,
                    finishReason: $response->finishReason,
                    toolCalls: $response->toolCalls,
                    usage: new Usage(
                        textInputTokens: $totalPromptTokens,
                        textOutputTokens: $totalCompletionTokens,
                    ),
                    meta: $response->meta,
                    steps: $steps,
                    messages: $messages,
                    raw: $response->raw,
                );
            }

            // Store current response as a step
            $steps[] = $response;

            // Append the assistant's tool call message to conversation
            $messages[] = new ToolCallMessage($response->toolCalls);

            // Execute all tool calls
            $toolResults = $executor->execute($response->toolCalls);

            // Append tool results to conversation
            $messages[] = new ToolResultMessage($toolResults);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Sends a structured output request to OpenAI.
     *
     * Delegates to AbstractDriver::executeStructured() which handles
     * schema generation, mode-specific options, extraction, deserialization,
     * validation, and automatic retries.
     */
    public function structured(StructuredRequest $request): StructuredResponse
    {
        return $this->executeStructured($request);
    }

    /**
     * {@inheritdoc}
     *
     * Sends an embedding request to OpenAI.
     *
     * Flow:
     * 1. Build payload: {model, input, dimensions?}
     * 2. POST to /embeddings
     * 3. Parse each data[].embedding → Embedding VO
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse
    {
        $payload = [
            'model' => $request->model,
            'input' => $request->input,
        ];

        if ($request->dimensions !== null) {
            $payload['dimensions'] = $request->dimensions;
        }

        // Merge passthrough options
        foreach ($request->options as $key => $value) {
            $payload[$key] = $value;
        }

        $result = $this->sendRequest('POST', '/embeddings', $payload);

        return OpenAIResponseParser::parseEmbeddingResponse($result['data'], $result['rateLimits']);
    }

    /**
     * {@inheritdoc}
     *
     * Streams a chat completion response from OpenAI via SSE.
     *
     * Flow:
     * 1. Build payload with stream: true
     * 2. Send stream request to /chat/completions
     * 3. Yield from OpenAIStreamParser::parse() which reads SSE line by line
     *
     * @return Generator<int, StreamChunkInterface> Generator yielding stream chunks.
     */
    public function stream(TextRequest $request): Generator
    {
        $payload = OpenAIPayloadBuilder::buildTextPayload($request, stream: true);

        $response = $this->sendStreamRequest('POST', '/chat/completions', $payload);

        yield from OpenAIStreamParser::parse($response->getBody());
    }

    /**
     * {@inheritdoc}
     *
     * Generates images using OpenAI's DALL-E API.
     *
     * Flow:
     * 1. Build payload from ImageRequest parameters
     * 2. POST to /images/generations
     * 3. Parse response into GeneratedImage VOs
     */
    public function image(ImageRequest $request): ImageResponse
    {
        $payload = $this->buildImagePayload($request);
        $result = $this->sendRequest('POST', '/images/generations', $payload);
        $data = $result['data'];

        $images = [];
        foreach ($data['data'] ?? [] as $imageData) {
            $images[] = new GeneratedImage(
                url: $imageData['url'] ?? null,
                base64: $imageData['b64_json'] ?? null,
                revisedPrompt: $imageData['revised_prompt'] ?? null,
            );
        }

        $usage = isset($data['usage'])
            ? Usage::fromOpenAI($data['usage'])
            : $this->syntheticUsage($request);

        return new ImageResponse(
            images: $images,
            usage: $usage,
            meta: new Meta(
                id: $data['id'] ?? null,
                model: $data['model'] ?? $request->model,
                rateLimits: $result['rateLimits'],
            ),
            finishReason: FinishReason::Stop,
            raw: $data,
        );
    }

    /**
     * {@inheritdoc}
     *
     * Text-to-Speech using OpenAI's TTS API (tts-1, tts-1-hd).
     *
     * Flow:
     * 1. Build payload from SpeechRequest parameters
     * 2. POST to /audio/speech
     * 3. Read raw audio bytes from response body
     */
    public function speak(SpeechRequest $request): SpeechResponse
    {
        $payload = [
            'model' => $request->model,
            'input' => $request->input,
            'voice' => $request->voice,
            'response_format' => $request->responseFormat,
            'speed' => $request->speed,
        ];

        // TTS returns binary audio, not JSON — use sendStreamRequest to get raw body
        $response = $this->sendStreamRequest('POST', '/audio/speech', $payload);
        $audioContent = $response->getBody()->getContents();

        // Map response format to MIME type
        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
        ];
        $format = $mimeTypes[$request->responseFormat] ?? 'audio/mpeg';

        $rateLimits = $this->extractRateLimits($response->getHeaders());

        return new SpeechResponse(
            audioContent: $audioContent,
            format: $format,
            usage: new Usage(),
            meta: new Meta(rateLimits: $rateLimits),
        );
    }

    /**
     * {@inheritdoc}
     *
     * Speech-to-Text using OpenAI's Whisper API.
     *
     * Flow:
     * 1. Build multipart form data from TranscriptionRequest
     * 2. POST to /audio/transcriptions
     * 3. Parse JSON response into TranscriptionResponse
     *
     * Note: Whisper uses multipart/form-data, not JSON. This method builds
     * the request manually using the PSR-17 factories.
     */
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        // Build multipart boundary
        $boundary = 'nexusai_' . bin2hex(random_bytes(16));
        $body = $this->buildMultipartBody($boundary, $request);

        // Create PSR-7 request manually for multipart
        $psrRequest = $this->requestFactory->createRequest('POST', $this->baseUrl . '/audio/transcriptions');

        foreach ($this->getAuthHeaders() as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        $psrRequest = $psrRequest->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary);
        $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($body));

        $response = $this->httpClient->sendRequest($psrRequest);

        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $rateLimits = $this->extractRateLimits($response->getHeaders());

        return new TranscriptionResponse(
            text: $data['text'] ?? '',
            language: $data['language'] ?? null,
            duration: isset($data['duration']) ? (float)$data['duration'] : null,
            segments: $data['segments'] ?? null,
            usage: new Usage(),
            meta: new Meta(rateLimits: $rateLimits),
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildImagePayload(ImageRequest $request): array
    {
        $payload = [
            'prompt' => $request->prompt,
            'model' => $request->model,
            'n' => $request->n,
        ];

        $provider = $this->getProviderName();

        if ($provider === 'openai') {
            if ($request->outputFormat !== null) {
                $payload['output_format'] = $request->outputFormat;
            }
            if ($request->size !== null) {
                $payload['size'] = $request->size;
            }
            if ($request->quality !== null) {
                $payload['quality'] = $request->quality;
            }
            if ($request->background !== null) {
                $payload['background'] = $request->background;
            }
            if ($request->outputCompression !== null) {
                $payload['output_compression'] = $request->outputCompression;
            }
            if ($request->moderation !== null) {
                $payload['moderation'] = $request->moderation;
            }
        } elseif ($provider === 'xai') {
            if ($request->responseFormat !== null) {
                $payload['response_format'] = $request->responseFormat;
            }
            if ($request->resolution !== null) {
                $payload['resolution'] = $request->resolution;
            }
        } else {
            if ($request->size !== null) {
                $payload['size'] = $request->size;
            }
            if ($request->quality !== null) {
                $payload['quality'] = $request->quality;
            }
            if ($request->outputFormat !== null) {
                $payload['output_format'] = $request->outputFormat;
            }
            if ($request->responseFormat !== null) {
                $payload['response_format'] = $request->responseFormat;
            }
        }

        foreach ($request->options as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    private function syntheticUsage(ImageRequest $request): Usage
    {
        $tokensPerImage = $request->resolution === '2k' ? 2_500 : 1_250;
        $textEstimate = (int) (strlen($request->prompt) / 4);

        return new Usage(
            textInputTokens: $textEstimate,
            imageOutputTokens: $request->n * $tokensPerImage,
        );
    }

    /**
     * {@inheritdoc}
     *
     * OpenAI supports: text, structured, embeddings, streaming, tools, images, audio.
     */
    public function supports(string $capability): bool
    {
        return in_array($capability, [
            self::CAPABILITY_TEXT,
            self::CAPABILITY_STRUCTURED,
            self::CAPABILITY_EMBEDDINGS,
            self::CAPABILITY_STREAMING,
            self::CAPABILITY_TOOLS,
            self::CAPABILITY_IMAGES,
            self::CAPABILITY_AUDIO,
        ], true);
    }

    /** {@inheritdoc} */
    public function getProviderName(): string
    {
        $baseUrl = strtolower($this->baseUrl);

        return match (true) {
            str_contains($baseUrl, 'x.ai') => 'xai',
            str_contains($baseUrl, 'groq.com') => 'groq',
            str_contains($baseUrl, 'mistral.ai') => 'mistral',
            str_contains($baseUrl, 'perplexity.ai') => 'perplexity',
            default => 'openai',
        };
    }

    /**
     * {@inheritdoc}
     *
     * Extracts rate limit information from OpenAI-specific response headers.
     *
     * OpenAI headers:
     * - x-ratelimit-limit-requests / x-ratelimit-remaining-requests / x-ratelimit-reset-requests
     * - x-ratelimit-limit-tokens / x-ratelimit-remaining-tokens / x-ratelimit-reset-tokens
     *
     * @param array<string, array<string>> $headers HTTP response headers.
     * @return array<RateLimitInfo> Extracted rate limit information.
     */
    protected function extractRateLimits(array $headers): array
    {
        $rateLimits = [];

        // Normalize headers to lowercase for consistent access
        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = $values[0] ?? '';
        }

        // Requests rate limit
        $requestLimit = $normalized['x-ratelimit-limit-requests'] ?? null;
        if ($requestLimit !== null) {
            $rateLimits[] = new RateLimitInfo(
                name: 'requests',
                limit: (int)$requestLimit,
                remaining: isset($normalized['x-ratelimit-remaining-requests'])
                    ? (int)$normalized['x-ratelimit-remaining-requests']
                    : null,
                resetsAt: isset($normalized['x-ratelimit-reset-requests'])
                    ? $this->parseResetTime($normalized['x-ratelimit-reset-requests'])
                    : null,
            );
        }

        // Tokens rate limit
        $tokenLimit = $normalized['x-ratelimit-limit-tokens'] ?? null;
        if ($tokenLimit !== null) {
            $rateLimits[] = new RateLimitInfo(
                name: 'tokens',
                limit: (int)$tokenLimit,
                remaining: isset($normalized['x-ratelimit-remaining-tokens'])
                    ? (int)$normalized['x-ratelimit-remaining-tokens']
                    : null,
                resetsAt: isset($normalized['x-ratelimit-reset-tokens'])
                    ? $this->parseResetTime($normalized['x-ratelimit-reset-tokens'])
                    : null,
            );
        }

        return $rateLimits;
    }

    /**
     * Parses OpenAI reset time strings into DateTimeImmutable.
     *
     * OpenAI sends reset times in various formats:
     * - Duration: "6m0s", "2m14.528s", "432ms"
     * - ISO 8601: "2024-01-01T00:00:00Z"
     *
     * For duration formats, calculates the absolute time from now.
     *
     * @param string $resetValue The raw reset time string from the header.
     * @return DateTimeImmutable|null The parsed reset time, or null on failure.
     */
    private function parseResetTime(string $resetValue): ?DateTimeImmutable
    {
        // Try ISO 8601 first
        $date = DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $resetValue);
        if ($date !== false) {
            return $date;
        }

        // Parse duration format (e.g., "6m0s", "2m14.528s", "432ms")
        $seconds = 0.0;

        if (preg_match('/(\d+)m/', $resetValue, $matches)) {
            $seconds += (int)$matches[1] * 60;
        }
        if (preg_match('/([\d.]+)s/', $resetValue, $matches)) {
            $seconds += (float)$matches[1];
        }
        if (preg_match('/(\d+)ms/', $resetValue, $matches)) {
            $seconds += (int)$matches[1] / 1000;
        }

        if ($seconds > 0) {
            return new DateTimeImmutable('+' . (int)ceil($seconds) . ' seconds');
        }

        return null;
    }

    /**
     * Builds a multipart/form-data body for audio transcription requests.
     *
     * @param string $boundary The multipart boundary string.
     * @param TranscriptionRequest $request The transcription request.
     * @return string The complete multipart body.
     */
    private function buildMultipartBody(string $boundary, TranscriptionRequest $request): string
    {
        $body = '';

        // File field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$request->audioFilename}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $request->audioContent;
        $body .= "\r\n";

        // Model field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= $request->model;
        $body .= "\r\n";

        // Response format field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
        $body .= $request->responseFormat;
        $body .= "\r\n";

        // Language field (optional)
        if ($request->language !== null) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= $request->language;
            $body .= "\r\n";
        }

        // Close boundary
        $body .= "--{$boundary}--\r\n";

        return $body;
    }
}
