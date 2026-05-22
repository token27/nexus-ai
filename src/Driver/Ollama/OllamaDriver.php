<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Ollama;

use Generator;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Driver\AbstractDriver;
use Token27\NexusAI\Driver\OpenAI\OpenAIResponseParser;
use Token27\NexusAI\Driver\OpenAI\OpenAIStreamParser;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Concrete driver for Ollama's OpenAI-compatible API.
 *
 * Uses the OpenAI-compatible endpoint at `/v1/chat/completions`.
 * Reuses OpenAI's ResponseParser and StreamParser since the response format
 * is identical. Only the PayloadBuilder has minor differences.
 *
 * Key differences from OpenAI:
 * - Base URL: `http://localhost:11434/v1` (local server)
 * - No auth required (API key can be empty)
 * - Uses OllamaPayloadBuilder for Ollama-specific option handling
 * - Does NOT support embeddings via this endpoint
 *
 * @see \Token27\NexusAI\Driver\AbstractDriver
 * @see \Token27\NexusAI\Driver\Ollama\OllamaPayloadBuilder
 */
final class OllamaDriver extends AbstractDriver
{
    /**
     * {@inheritdoc}
     *
     * Sends a chat completion request to Ollama with tool loop support.
     *
     * Delegates to AbstractDriver::executeWithToolLoop() for message accumulation,
     * tool execution, and loop termination.
     */
    public function text(TextRequest $request): TextResponse
    {
        return $this->executeWithToolLoop($request, function (TextRequest $req, array $messages, int $step): TextResponse {
            $payload = OllamaPayloadBuilder::buildTextPayload($req);

            if ($step > 0) {
                $payload['messages'] = \Token27\NexusAI\Driver\OpenAI\OpenAIPayloadBuilder::mapMessages($messages);
            }

            $result = $this->sendRequest('POST', '/chat/completions', $payload);

            return OpenAIResponseParser::parseTextResponse($result['data'], $result['rateLimits']);
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

        $payload = OllamaPayloadBuilder::buildTextPayload($textRequest);
        $result = $this->sendRequest('POST', '/chat/completions', $payload);
        $textResponse = OpenAIResponseParser::parseTextResponse($result['data'], $result['rateLimits']);

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
     * @throws \RuntimeException Ollama embeddings use `/api/embeddings` (not OpenAI-compatible).
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse
    {
        throw new \RuntimeException(
            'Ollama embeddings use the native /api/embeddings endpoint. Not supported via OpenAI-compatible API.',
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return Generator<int, StreamChunkInterface>
     */
    public function stream(TextRequest $request): Generator
    {
        $payload = OllamaPayloadBuilder::buildTextPayload($request, stream: true);
        $response = $this->sendStreamRequest('POST', '/chat/completions', $payload);

        yield from OpenAIStreamParser::parse($response->getBody());
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
        ], true);
    }

    /** {@inheritdoc} */
    public function getProviderName(): string
    {
        return 'ollama';
    }

    /**
     * {@inheritdoc}
     *
     * Ollama does not require authentication.
     * Returns empty headers (no auth needed for local server).
     *
     * @return array<string, string> Empty auth headers.
     */
    protected function getAuthHeaders(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * Ollama does not provide rate limit headers.
     *
     * @param array<string, array<string>> $headers HTTP response headers.
     * @return array<RateLimitInfo> Empty array.
     */
    protected function extractRateLimits(array $headers): array
    {
        return [];
    }
}
