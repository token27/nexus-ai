<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Anthropic;

use DateTimeImmutable;
use Generator;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Driver\AbstractDriver;
use Token27\NexusAI\Enum\StructuredMode;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Concrete driver for the Anthropic Messages API (Claude).
 *
 * Key differences from OpenAI:
 * - Auth via `x-api-key` header (not Bearer token)
 * - System prompt in top-level `system` field (not in messages)
 * - Endpoint: `/messages` (not `/chat/completions`)
 * - Tool calls use `tool_use`/`tool_result` content blocks
 * - Stream uses typed events (message_start, content_block_delta, etc.)
 *
 * @see \Token27\NexusAI\Driver\AbstractDriver
 * @see \Token27\NexusAI\Driver\Anthropic\AnthropicPayloadBuilder
 * @see \Token27\NexusAI\Driver\Anthropic\AnthropicResponseParser
 * @see \Token27\NexusAI\Driver\Anthropic\AnthropicStreamParser
 */
final class AnthropicDriver extends AbstractDriver
{
    /** Anthropic API version header value. */
    private const string API_VERSION = '2023-06-01';

    /**
     * {@inheritdoc}
     *
     * Sends a Messages request to Anthropic with tool loop support.
     *
     * Delegates to AbstractDriver::executeWithToolLoop() for message accumulation,
     * tool execution, and loop termination.
     */
    public function text(TextRequest $request): TextResponse
    {
        return $this->executeWithToolLoop($request, function (TextRequest $req, array $messages, int $step): TextResponse {
            $payload = AnthropicPayloadBuilder::buildTextPayload($req);

            if ($step > 0) {
                // Rebuild messages from accumulated history (without system)
                $nonSystem = array_values(array_filter(
                    $messages,
                    fn (Message $m) => !$m instanceof \Token27\NexusAI\Message\SystemMessage,
                ));
                $payload['messages'] = AnthropicPayloadBuilder::mapMessages($nonSystem);
            }

            $result = $this->sendRequest('POST', '/messages', $payload);

            return AnthropicResponseParser::parseTextResponse($result['data'], $result['rateLimits']);
        });
    }

    /**
     * {@inheritdoc}
     *
     * Structured output via Anthropic. Delegates to text() with a minimal wrapper.
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

        $payload = AnthropicPayloadBuilder::buildTextPayload($textRequest);
        $result = $this->sendRequest('POST', '/messages', $payload);
        $textResponse = AnthropicResponseParser::parseTextResponse($result['data'], $result['rateLimits']);

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
     * Anthropic does not support embeddings.
     *
     * @throws \RuntimeException Always — Anthropic has no embeddings endpoint.
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse
    {
        throw new \RuntimeException('Anthropic does not support embeddings. Use OpenAI or another provider.');
    }

    /**
     * {@inheritdoc}
     *
     * Streams a Messages response from Anthropic via SSE.
     *
     * @return Generator<int, StreamChunkInterface>
     */
    public function stream(TextRequest $request): Generator
    {
        $payload = AnthropicPayloadBuilder::buildTextPayload($request, stream: true);
        $response = $this->sendStreamRequest('POST', '/messages', $payload);

        yield from AnthropicStreamParser::parse($response->getBody());
    }

    /**
     * {@inheritdoc}
     *
     * Anthropic supports: text, structured, streaming, tools.
     * Does NOT support: embeddings, images, audio.
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
        return 'anthropic';
    }

    /**
     * {@inheritdoc}
     *
     * Anthropic resolves StructuredMode::Auto to Tool mode.
     * Anthropic's native structured output uses tool calling via
     * the Messages API without native response_format support.
     */
    protected function resolveStructuredMode(StructuredMode $mode): StructuredMode
    {
        if ($mode !== StructuredMode::Auto) {
            return $mode;
        }

        return StructuredMode::Tool;
    }

    /**
     * {@inheritdoc}
     *
     * Anthropic uses `x-api-key` header instead of Bearer token.
     * Also requires the `anthropic-version` header.
     *
     * @return array<string, string> Auth headers.
     */
    protected function getAuthHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Extracts rate limit info from Anthropic-specific headers.
     *
     * Headers:
     * - anthropic-ratelimit-requests-limit / remaining / reset
     * - anthropic-ratelimit-tokens-limit / remaining / reset
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

        // Requests rate limit
        $requestLimit = $normalized['anthropic-ratelimit-requests-limit'] ?? null;
        if ($requestLimit !== null) {
            $rateLimits[] = new RateLimitInfo(
                name: 'requests',
                limit: (int) $requestLimit,
                remaining: isset($normalized['anthropic-ratelimit-requests-remaining'])
                    ? (int) $normalized['anthropic-ratelimit-requests-remaining'] : null,
                resetsAt: isset($normalized['anthropic-ratelimit-requests-reset'])
                    ? $this->parseResetTime($normalized['anthropic-ratelimit-requests-reset']) : null,
            );
        }

        // Tokens rate limit
        $tokenLimit = $normalized['anthropic-ratelimit-tokens-limit'] ?? null;
        if ($tokenLimit !== null) {
            $rateLimits[] = new RateLimitInfo(
                name: 'tokens',
                limit: (int) $tokenLimit,
                remaining: isset($normalized['anthropic-ratelimit-tokens-remaining'])
                    ? (int) $normalized['anthropic-ratelimit-tokens-remaining'] : null,
                resetsAt: isset($normalized['anthropic-ratelimit-tokens-reset'])
                    ? $this->parseResetTime($normalized['anthropic-ratelimit-tokens-reset']) : null,
            );
        }

        return $rateLimits;
    }

    /**
     * Parses Anthropic reset time strings (ISO 8601) into DateTimeImmutable.
     *
     * @param string $resetValue The raw reset time string.
     * @return DateTimeImmutable|null The parsed time, or null on failure.
     */
    private function parseResetTime(string $resetValue): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $resetValue);
        if ($date !== false) {
            return $date;
        }

        // Try ISO 8601 with microseconds
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $resetValue);

        return $date !== false ? $date : null;
    }
}
