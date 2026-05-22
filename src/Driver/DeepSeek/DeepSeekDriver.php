<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\DeepSeek;

use Generator;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Driver\AbstractDriver;
use Token27\NexusAI\Driver\OpenAI\OpenAIPayloadBuilder;
use Token27\NexusAI\Driver\OpenAI\OpenAIResponseParser;
use Token27\NexusAI\Driver\OpenAI\OpenAIStreamParser;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\Meta;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Concrete driver for the DeepSeek API.
 *
 * DeepSeek is 99% compatible with the OpenAI API. This driver reuses
 * OpenAIPayloadBuilder, OpenAIResponseParser, and OpenAIStreamParser directly.
 *
 * The only unique feature is `reasoning_content` support for deepseek-reasoner:
 * when the model includes its chain-of-thought reasoning, it appears in
 * `message.reasoning_content` alongside the normal `message.content`.
 *
 * @see \Token27\NexusAI\Driver\AbstractDriver
 */
final class DeepSeekDriver extends AbstractDriver
{
    /**
     * {@inheritdoc}
     *
     * Sends a chat completion request to DeepSeek with tool loop and
     * reasoning_content extraction support.
     *
     * Delegates to AbstractDriver::executeWithToolLoop() for message accumulation,
     * tool execution, and loop termination.
     */
    public function text(TextRequest $request): TextResponse
    {
        return $this->executeWithToolLoop($request, function (TextRequest $req, array $messages, int $step): TextResponse {
            $payload = OpenAIPayloadBuilder::buildTextPayload($req);

            if ($step > 0) {
                $payload['messages'] = OpenAIPayloadBuilder::mapMessages($messages);
            }

            $result = $this->sendRequest('POST', '/chat/completions', $payload);

            // Parse with standard OpenAI parser
            $response = OpenAIResponseParser::parseTextResponse($result['data'], $result['rateLimits']);

            // Extract reasoning_content if present (deepseek-reasoner)
            return $this->enrichWithReasoningContent($response, $result['data']);
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

        $payload = OpenAIPayloadBuilder::buildTextPayload($textRequest);
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
     * @throws \RuntimeException DeepSeek does not support embeddings.
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse
    {
        throw new \RuntimeException('DeepSeek does not support embeddings.');
    }

    /**
     * {@inheritdoc}
     *
     * @return Generator<int, StreamChunkInterface>
     */
    public function stream(TextRequest $request): Generator
    {
        $payload = OpenAIPayloadBuilder::buildTextPayload($request, stream: true);
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
        return 'deepseek';
    }

    /**
     * {@inheritdoc}
     *
     * Extracts rate limits from DeepSeek headers (same format as OpenAI).
     *
     * @param array<string, array<string>> $headers HTTP response headers.
     * @return array<RateLimitInfo> Extracted rate limit information.
     */
    protected function extractRateLimits(array $headers): array
    {
        $rateLimits = [];
        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = $values[0] ?? '';
        }

        $requestLimit = $normalized['x-ratelimit-limit-requests'] ?? null;
        if ($requestLimit !== null) {
            $rateLimits[] = new RateLimitInfo(
                name: 'requests',
                limit: (int) $requestLimit,
                remaining: isset($normalized['x-ratelimit-remaining-requests'])
                    ? (int) $normalized['x-ratelimit-remaining-requests'] : null,
                resetsAt: null,
            );
        }

        $tokenLimit = $normalized['x-ratelimit-limit-tokens'] ?? null;
        if ($tokenLimit !== null) {
            $rateLimits[] = new RateLimitInfo(
                name: 'tokens',
                limit: (int) $tokenLimit,
                remaining: isset($normalized['x-ratelimit-remaining-tokens'])
                    ? (int) $normalized['x-ratelimit-remaining-tokens'] : null,
                resetsAt: null,
            );
        }

        return $rateLimits;
    }

    /**
     * Enriches a TextResponse with reasoning_content from DeepSeek's deepseek-reasoner.
     *
     * When using deepseek-reasoner, the response includes `reasoning_content`
     * in the message object alongside the normal `content`. This method extracts
     * that reasoning and stores it in the raw response metadata.
     *
     * @param TextResponse $response The parsed response.
     * @param array<string, mixed> $data Raw JSON response data.
     * @return TextResponse The enriched response (may be the same instance if no reasoning).
     */
    private function enrichWithReasoningContent(TextResponse $response, array $data): TextResponse
    {
        $message = $data['choices'][0]['message'] ?? [];
        $reasoningContent = $message['reasoning_content'] ?? null;

        if ($reasoningContent === null || $reasoningContent === '') {
            return $response;
        }

        // Store reasoning_content in the raw data and create an enriched meta
        $enrichedRaw = $response->raw ?? [];
        $enrichedRaw['_reasoning_content'] = $reasoningContent;

        return new TextResponse(
            text: $response->text,
            finishReason: $response->finishReason,
            toolCalls: $response->toolCalls,
            usage: $response->usage,
            meta: $response->meta,
            steps: $response->steps,
            messages: $response->messages,
            raw: $enrichedRaw,
        );
    }
}
