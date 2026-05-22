<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Gemini;

use Generator;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Token27\NexusAI\Contract\ImageCapableInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Driver\AbstractDriver;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Enum\StructuredMode;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\SystemMessage;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\ImageRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\ImageResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\GeneratedImage;
use Token27\NexusAI\ValueObject\Meta;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Concrete driver for Google Gemini API.
 *
 * Key differences from OpenAI:
 * - Auth via query param `?key=` (NOT headers)
 * - Endpoint: `/models/{model}:generateContent`
 * - Stream endpoint: `/models/{model}:streamGenerateContent?alt=sse`
 * - System prompt in `systemInstruction` field
 * - Roles: `user`/`model` (NOT `assistant`)
 * - Response in `candidates[0].content.parts[0].text`
 *
 * @see \Token27\NexusAI\Driver\AbstractDriver
 */
final class GeminiDriver extends AbstractDriver implements ImageCapableInterface
{
    /**
     * {@inheritdoc}
     *
     * Sends a generateContent request to Gemini with tool loop support.
     *
     * Delegates to AbstractDriver::executeWithToolLoop() for message accumulation,
     * tool execution, and loop termination. Overrides shouldContinueToolLoop()
     * for Gemini's unique tool call continuation behavior.
     */
    public function text(TextRequest $request): TextResponse
    {
        return $this->executeWithToolLoop($request, function (TextRequest $req, array $messages, int $step): TextResponse {
            $payload = GeminiPayloadBuilder::buildTextPayload($req);

            if ($step > 0) {
                $nonSystem = array_values(array_filter(
                    $messages,
                    fn (Message $m) => !$m instanceof SystemMessage,
                ));
                $payload['contents'] = GeminiPayloadBuilder::mapMessages($nonSystem);
            }

            $endpoint = "/models/{$req->model}:generateContent";
            $result = $this->sendGeminiRequest('POST', $endpoint, $payload);

            return GeminiResponseParser::parseTextResponse($result['data'], $result['rateLimits']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function structured(StructuredRequest $request): StructuredResponse
    {
        $textRequest = new TextRequest(
            provider: $request->provider,
            model: $request->model,
            messages: $request->messages,
            systemPrompt: $request->systemPrompt,
            maxTokens: $request->maxTokens,
            temperature: $request->temperature,
            topP: $request->topP,
            tools: $request->tools,
            toolChoice: $request->toolChoice,
            maxSteps: $request->maxSteps,
            options: $request->options,
        );

        $payload = GeminiPayloadBuilder::buildTextPayload($textRequest);
        $endpoint = "/models/{$request->model}:generateContent";
        $result = $this->sendGeminiRequest('POST', $endpoint, $payload);
        $textResponse = GeminiResponseParser::parseTextResponse($result['data'], $result['rateLimits']);

        return new StructuredResponse(
            object: (object) ['text' => $textResponse->text],
            text: $textResponse->text,
            finishReason: $textResponse->finishReason,
            usage: $textResponse->usage,
            meta: $textResponse->meta,
            raw: $textResponse->raw,
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException Gemini embeddings use a different endpoint not yet implemented.
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse
    {
        throw new \RuntimeException(
            'Gemini embeddings require the /models/{model}:embedContent endpoint. Not yet implemented.',
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return Generator<int, StreamChunkInterface>
     */
    public function stream(TextRequest $request): Generator
    {
        $payload = GeminiPayloadBuilder::buildTextPayload($request, stream: true);
        $endpoint = "/models/{$request->model}:streamGenerateContent";
        $response = $this->sendGeminiStreamRequest('POST', $endpoint, $payload);

        yield from GeminiStreamParser::parse($response->getBody());
    }

    /**
     * {@inheritdoc}
     *
     * Generates images using Gemini native image-generation models.
     *
     * Gemini returns multimodal parts; image bytes are returned in
     * `candidates[...].content.parts[].inlineData.data`.
     */
    public function image(ImageRequest $request): ImageResponse
    {
        $payload = $this->buildImagePayload($request);
        $endpoint = "/models/{$request->model}:generateContent";
        $result = $this->sendGeminiRequest('POST', $endpoint, $payload);
        $data = $result['data'];

        $images = [];
        foreach (($data['candidates'] ?? []) as $candidate) {
            $parts = $candidate['content']['parts'] ?? [];
            if (!is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                if (!is_array($part)) {
                    continue;
                }

                $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;
                if (!is_array($inlineData)) {
                    continue;
                }

                $base64 = $inlineData['data'] ?? null;
                if (!is_string($base64) || $base64 === '') {
                    continue;
                }

                $images[] = new GeneratedImage(base64: $base64);
            }
        }

        $usage = GeminiResponseParser::parseGeminiUsage($data['usageMetadata'] ?? []);
        $finishReason = GeminiResponseParser::mapFinishReason(
            $data['candidates'][0]['finishReason'] ?? null,
        );

        return new ImageResponse(
            images: $images,
            usage: $usage,
            meta: new Meta(
                id: isset($data['responseId']) && is_string($data['responseId']) ? $data['responseId'] : null,
                model: isset($data['modelVersion']) && is_string($data['modelVersion']) ? $data['modelVersion'] : $request->model,
                rateLimits: $result['rateLimits'],
            ),
            finishReason: $finishReason === FinishReason::Unknown ? FinishReason::Stop : $finishReason,
            raw: $data,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $capability): bool
    {
        return in_array($capability, [
            self::CAPABILITY_TEXT,
            self::CAPABILITY_STRUCTURED,
            self::CAPABILITY_STREAMING,
            self::CAPABILITY_TOOLS,
            self::CAPABILITY_IMAGES,
        ], true);
    }

    /** {@inheritdoc} */
    public function getProviderName(): string
    {
        return 'gemini';
    }

    /**
     * {@inheritdoc}
     *
     * Gemini may return tool calls even when finishReason is STOP.
     * This override continues the tool loop when tool calls are present
     * regardless of the finish reason, as long as an executor is available
     * and the step limit hasn't been reached.
     */
    protected function shouldContinueToolLoop(
        \Token27\NexusAI\Response\TextResponse $response,
        ?\Token27\NexusAI\Tool\ToolExecutor $executor,
        int $step,
        int $maxSteps,
    ): bool {
        return $response->toolCalls !== []
            && $executor !== null
            && $step < $maxSteps;
    }

    /**
     * {@inheritdoc}
     *
     * Gemini resolves StructuredMode::Auto to Json mode.
     * Gemini supports response_format json_object natively.
     */
    protected function resolveStructuredMode(StructuredMode $mode): StructuredMode
    {
        if ($mode !== StructuredMode::Auto) {
            return $mode;
        }

        return StructuredMode::Json;
    }

    /**
     * {@inheritdoc}
     *
     * Gemini does NOT use auth headers — auth is via query param.
     * Returns empty array; URL modification happens in sendGeminiRequest().
     *
     * @return array<string, string> Empty array.
     */
    protected function getAuthHeaders(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * Gemini doesn't provide standard rate limit headers.
     *
     * @param array<string, array<string>> $headers HTTP response headers.
     * @return array<RateLimitInfo> Empty array.
     */
    protected function extractRateLimits(array $headers): array
    {
        return [];
    }

    /**
     * Sends an HTTP request to Gemini with API key as query parameter.
     *
     * Gemini authenticates via `?key=API_KEY` query parameter instead of headers.
     *
     * @param string $method HTTP method.
     * @param string $path API path (e.g., '/models/gemini-pro:generateContent').
     * @param array<string, mixed> $payload Request payload.
     * @return array{data: array<string, mixed>, rateLimits: array<RateLimitInfo>}
     */
    private function sendGeminiRequest(string $method, string $path, array $payload): array
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        $url = $this->baseUrl . $path . $separator . 'key=' . $this->apiKey;

        $request = $this->requestFactory->createRequest($method, $url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $body = $this->streamFactory->createStream(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $request = $request->withBody($body);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        /** @var array<string, mixed> $jsonData */
        $jsonData = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $rateLimits = $this->extractRateLimits($response->getHeaders());

        return ['data' => $jsonData, 'rateLimits' => $rateLimits];
    }

    /**
     * Sends a streaming request to Gemini with API key as query parameter.
     *
     * Uses the `?alt=sse` query parameter to enable SSE streaming.
     *
     * @param string $method HTTP method.
     * @param string $path API path.
     * @param array<string, mixed> $payload Request payload.
     * @return PsrResponseInterface The raw PSR-7 response.
     */
    private function sendGeminiStreamRequest(string $method, string $path, array $payload): PsrResponseInterface
    {
        $url = $this->baseUrl . $path . '?alt=sse&key=' . $this->apiKey;

        $request = $this->requestFactory->createRequest($method, $url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withHeader('Accept', 'text/event-stream');

        $body = $this->streamFactory->createStream(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $request = $request->withBody($body);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildImagePayload(ImageRequest $request): array
    {
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $request->prompt]],
            ]],
        ];

        $generationConfig = [
            'responseModalities' => ['IMAGE'],
        ];
        $imageConfig = [];

        if ($request->n > 1) {
            // Gemini image-capable models can return multiple candidates.
            $generationConfig['candidateCount'] = $request->n;
        }

        if ($request->size !== null) {
            $aspectRatio = $this->mapSizeToAspectRatio($request->size);
            if ($aspectRatio !== null) {
                $imageConfig['aspectRatio'] = $aspectRatio;
            }

            $imageSize = $this->mapSizeToImageSize($request->size);
            if ($imageSize !== null) {
                $imageConfig['imageSize'] = $imageSize;
            }
        }

        if ($imageConfig !== []) {
            $generationConfig['imageConfig'] = $imageConfig;
        }

        $payload['generationConfig'] = $generationConfig;

        foreach ($request->options as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    private function mapSizeToAspectRatio(string $size): ?string
    {
        return match ($size) {
            '1024x1024' => '1:1',
            '512x512' => '1:1',
            '1536x1024' => '3:2',
            '1024x1536' => '2:3',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
            default => null,
        };
    }

    private function mapSizeToImageSize(string $size): ?string
    {
        return match ($size) {
            '512x512' => '512',
            '1024x1024' => '1K',
            '1536x1024', '1024x1536', '1792x1024', '1024x1792' => '2K',
            default => null,
        };
    }
}
