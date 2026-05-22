<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver;

use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Enum\StructuredMode;
use Token27\NexusAI\Exception\DriverException;
use Token27\NexusAI\Exception\ProviderOverloadedException;
use Token27\NexusAI\Exception\RateLimitException;
use Token27\NexusAI\Exception\RequestTooLargeException;
use Token27\NexusAI\Exception\StructuredOutputException;
use Token27\NexusAI\Message\AssistantMessage;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\ToolCallMessage;
use Token27\NexusAI\Message\ToolResultMessage;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\StructuredOutput\Deserializer;
use Token27\NexusAI\StructuredOutput\JsonExtractor;
use Token27\NexusAI\StructuredOutput\SchemaGenerator;
use Token27\NexusAI\StructuredOutput\Validation\Validator;
use Token27\NexusAI\Tool\ToolExecutor;
use Token27\NexusAI\Tool\ToolRegistry;
use Token27\NexusAI\ValueObject\RateLimitInfo;

/**
 * Base abstract class for ALL AI provider drivers.
 *
 * Handles the common HTTP transport layer via PSR-18 / PSR-17, including:
 * - Request construction with auth headers
 * - Response error handling (429, 413, 503/529 → typed exceptions)
 * - JSON encoding/decoding of payloads and responses
 * - Rate limit extraction delegation to concrete drivers
 * - Structured output pipeline (schema → request → extract → deserialize → validate → retry)
 *
 * Concrete drivers (OpenAIDriver, AnthropicDriver, etc.) extend this class
 * and implement domain-specific logic: text(), structured(), embeddings(), stream().
 *
 * Uses PSR-18 instead of Guzzle (Neuron AI) or Laravel Http (Prism) for
 * zero framework coupling — the user injects their own HTTP client.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIDriver
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * @param ClientInterface $httpClient PSR-18 HTTP client. Sends HTTP requests.
     * @param RequestFactoryInterface $requestFactory PSR-17 factory. Creates PSR-7 Request objects.
     * @param StreamFactoryInterface $streamFactory PSR-17 factory. Creates streams for request bodies.
     * @param string $apiKey API key for the provider.
     * @param string $baseUrl Base URL for the API (e.g., 'https://api.openai.com/v1').
     * @param array<string, mixed> $options Additional provider-specific configuration.
     */
    public function __construct(
        protected readonly ClientInterface $httpClient,
        protected readonly RequestFactoryInterface $requestFactory,
        protected readonly StreamFactoryInterface $streamFactory,
        protected readonly string $apiKey,
        protected readonly string $baseUrl,
        protected readonly array $options = [],
    ) {
    }

    /**
     * Returns the human-readable name of this provider.
     *
     * Used in error messages and exception construction.
     *
     * @return string Provider name (e.g., 'openai', 'anthropic').
     */
    abstract public function getProviderName(): string;

    /**
     * Extracts rate limiting information from HTTP response headers.
     *
     * Each provider uses different header naming conventions:
     * - OpenAI:    x-ratelimit-limit-requests, x-ratelimit-remaining-requests, ...
     * - Anthropic: anthropic-ratelimit-requests-limit, ...
     *
     * @param array<string, array<string>> $headers HTTP response headers.
     * @return array<RateLimitInfo> Extracted rate limit information.
     */
    abstract protected function extractRateLimits(array $headers): array;

    // ──────────────────────────────────────────────────────────────────
    // Structured Output Pipeline
    // ──────────────────────────────────────────────────────────────────

    /**
     * Executes the structured output pipeline: generate schema, send request,
     * extract JSON, deserialize, validate, and retry with LLM feedback if needed.
     *
     * Flow:
     * 1. Generate JSON Schema from the output class via SchemaGenerator
     * 2. Build a TextRequest with schema instruction in system prompt
     * 3. Send to the LLM via $this->text()
     * 4. Extract JSON from response via JsonExtractor
     * 5. Deserialize into the target DTO via Deserializer
     * 6. Validate the DTO via Validator
     * 7. If violations exist and attempts remain → retry with error feedback
     * 8. Return StructuredResponse with the hydrated DTO
     *
     * @param StructuredRequest $request The structured output request.
     * @return StructuredResponse The response containing the deserialized DTO.
     * @throws StructuredOutputException If all attempts fail.
     */
    protected function executeStructured(StructuredRequest $request): StructuredResponse
    {
        $schema = SchemaGenerator::generate($request->outputClass);
        $mode = $this->resolveStructuredMode($request->mode);

        // Build initial text request with schema instruction in system prompt
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $schemaPrompt = "You MUST respond with a valid JSON object matching this schema:\n{$schemaJson}\n\nRespond ONLY with the JSON object, no other text.";

        $systemPrompt = $request->systemPrompt
            ? $request->systemPrompt . "\n\n" . $schemaPrompt
            : $schemaPrompt;

        $messages = $request->messages;
        $violations = [];
        $textResponse = null;

        for ($attempt = 0; $attempt <= $request->maxRetries; $attempt++) {
            $textRequest = new TextRequest(
                provider: $request->provider,
                model: $request->model,
                messages: $messages,
                systemPrompt: $attempt === 0 ? $systemPrompt : null,
                maxTokens: $request->maxTokens,
                temperature: $request->temperature,
                topP: $request->topP,
                options: $this->buildStructuredOptions($request, $mode, $schema),
            );

            $textResponse = $this->text($textRequest);

            // Extract JSON
            try {
                $json = JsonExtractor::extract($textResponse->text);
            } catch (StructuredOutputException) {
                if ($attempt < $request->maxRetries) {
                    $messages = array_merge($messages, [
                        new AssistantMessage($textResponse->text),
                        new UserMessage('Your response was not valid JSON. Please respond ONLY with a valid JSON object.'),
                    ]);
                    continue;
                }
                throw new StructuredOutputException(
                    'Failed to extract JSON from response after ' . ($attempt + 1) . ' attempts',
                    responseText: $textResponse->text,
                    schema: $schema,
                );
            }

            // Deserialize
            try {
                $object = Deserializer::deserialize($json, $request->outputClass);
            } catch (StructuredOutputException $e) {
                if ($attempt < $request->maxRetries) {
                    $messages = array_merge($messages, [
                        new AssistantMessage($textResponse->text),
                        new UserMessage("JSON deserialization failed: {$e->getMessage()}. Please fix and return valid JSON."),
                    ]);
                    continue;
                }
                throw $e;
            }

            // Validate
            $violations = Validator::validate($object);
            if ($violations === []) {
                return new StructuredResponse(
                    object: $object,
                    text: $json,
                    finishReason: $textResponse->finishReason,
                    usage: $textResponse->usage,
                    meta: $textResponse->meta,
                    raw: $textResponse->raw,
                    attempts: $attempt + 1,
                    violations: [],
                );
            }

            // Retry with validation feedback
            if ($attempt < $request->maxRetries) {
                $violationText = implode("\n", array_map(fn ($v) => "- {$v}", $violations));
                $messages = array_merge($messages, [
                    new AssistantMessage($textResponse->text),
                    new UserMessage("Your JSON had validation errors:\n{$violationText}\nPlease fix and return the complete valid JSON."),
                ]);
            }
        }

        throw new StructuredOutputException(
            'Failed to get valid structured output after ' . ($request->maxRetries + 1) . ' attempts',
            responseText: $textResponse->text ?? '',
            schema: $schema,
            violations: $violations,
        );
    }

    /**
     * Resolves the best structured output mode for this provider.
     *
     * Default implementation resolves Auto → Json. Drivers can override
     * for provider-specific defaults (e.g., Anthropic → Tool, OpenAI → Native).
     *
     * @param StructuredMode $mode The requested mode.
     * @return StructuredMode The resolved mode.
     */
    protected function resolveStructuredMode(StructuredMode $mode): StructuredMode
    {
        if ($mode !== StructuredMode::Auto) {
            return $mode;
        }

        return StructuredMode::Json; // Default, overridable by drivers
    }

    /**
     * Builds provider-specific options for structured output mode.
     *
     * Adds response_format configuration based on the resolved mode.
     * Drivers can override for provider-specific JSON schema support.
     *
     * @param StructuredRequest $request The original request.
     * @param StructuredMode $mode The resolved structured mode.
     * @param array<string, mixed> $schema The generated JSON Schema.
     * @return array<string, mixed> The options array with response_format added.
     */
    protected function buildStructuredOptions(
        StructuredRequest $request,
        StructuredMode $mode,
        array $schema,
    ): array {
        $options = $request->options;

        if ($mode === StructuredMode::Json) {
            $options['response_format'] = ['type' => 'json_object'];
        } elseif ($mode === StructuredMode::Native) {
            $options['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => basename(str_replace('\\', '/', $request->outputClass)),
                    'schema' => $schema,
                    'strict' => true,
                ],
            ];
        }

        return $options;
    }

    // ──────────────────────────────────────────────────────────────────
    // Tool Loop
    // ──────────────────────────────────────────────────────────────────

    /**
     * Executes a text request with automatic tool loop support.
     *
     * Template method: concrete drivers supply a `$sendAndParse` callable
     * that builds the provider-specific payload, sends the HTTP request,
     * and parses the response. This method handles message accumulation,
     * tool execution, and loop termination.
     *
     * @param TextRequest $request The text generation request.
     * @param callable $sendAndParse fn(TextRequest $request, array<Message> $messages, int $step): TextResponse
     * @return TextResponse The final response with accumulated usage, steps, and messages.
     */
    protected function executeWithToolLoop(
        TextRequest $request,
        callable $sendAndParse,
    ): TextResponse {
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
            $currentRequest = new TextRequest(
                provider: $request->provider,
                model: $request->model,
                messages: $messages,
                systemPrompt: $step === 0 ? $request->systemPrompt : null,
                maxTokens: $request->maxTokens,
                temperature: $request->temperature,
                topP: $request->topP,
                tools: $request->tools,
                toolChoice: $request->toolChoice,
                maxSteps: $request->maxSteps,
                options: $request->options,
            );

            /** @var TextResponse $response */
            $response = $sendAndParse($currentRequest, $messages, $step);

            $totalPromptTokens += $response->usage->textInputTokens;
            $totalCompletionTokens += $response->usage->textOutputTokens;
            $step++;

            if ($executor === null || !$this->shouldContinueToolLoop($response, $executor, $step, $request->maxSteps)) {
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

            $steps[] = $response;
            $messages[] = new ToolCallMessage($response->toolCalls);
            $toolResults = $executor->execute($response->toolCalls);
            $messages[] = new ToolResultMessage($toolResults);
        }
    }

    /**
     * Determines whether the tool loop should continue after a response.
     *
     * Overridable hook for providers with non-standard tool call behavior.
     * Gemini, for example, may return tool calls even when finishReason is STOP.
     *
     * @param TextResponse $response The parsed response from the provider.
     * @param ToolExecutor|null $executor The tool executor, or null if no tools configured.
     * @param int $step Current step count (after increment).
     * @param int $maxSteps Maximum allowed steps.
     * @return bool True if the tool loop should continue.
     */
    protected function shouldContinueToolLoop(
        TextResponse $response,
        ?ToolExecutor $executor,
        int $step,
        int $maxSteps,
    ): bool {
        return $response->finishReason === FinishReason::ToolCalls
            && $executor !== null
            && $step < $maxSteps;
    }

    // ──────────────────────────────────────────────────────────────────
    // HTTP Transport
    // ──────────────────────────────────────────────────────────────────

    /**
     * Sends an HTTP request to the provider API and returns the parsed JSON response.
     *
     * Flow:
     * 1. Creates PSR-7 Request with method + full URL
     * 2. Adds auth headers and Content-Type
     * 3. JSON-encodes the payload as body
     * 4. Sends via PSR-18 client
     * 5. Checks for HTTP errors (status >= 400)
     * 6. JSON-decodes the response body
     * 7. Extracts rate limits from headers
     *
     * @param string $method HTTP method ('POST', 'GET', etc.).
     * @param string $path API path (e.g., '/chat/completions').
     * @param array<string, mixed> $payload Request payload to JSON-encode.
     * @return array{data: array<string, mixed>, rateLimits: array<RateLimitInfo>}
     *
     * @throws RateLimitException On HTTP 429.
     * @throws RequestTooLargeException On HTTP 413.
     * @throws ProviderOverloadedException On HTTP 503/529.
     * @throws DriverException On any other HTTP error.
     * @throws JsonException On JSON encoding/decoding failure.
     */
    protected function sendRequest(string $method, string $path, array $payload): array
    {
        $request = $this->requestFactory->createRequest($method, $this->baseUrl . $path);

        // Add authentication and content-type headers
        foreach ($this->getAuthHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request = $request->withHeader('Content-Type', 'application/json');

        // Create and attach the JSON body
        $body = $this->streamFactory->createStream(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $request = $request->withBody($body);

        // Send the request via PSR-18
        $response = $this->httpClient->sendRequest($request);

        // Check for HTTP errors
        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        // Decode the JSON response
        /** @var array<string, mixed> $jsonData */
        $jsonData = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        // Extract rate limits from response headers
        $rateLimits = $this->extractRateLimits($response->getHeaders());

        return ['data' => $jsonData, 'rateLimits' => $rateLimits];
    }

    /**
     * Sends an HTTP request for streaming responses.
     *
     * Unlike sendRequest(), this method does NOT consume the response body.
     * It returns the raw PSR-7 Response so the StreamParser can read the
     * body line-by-line as SSE events arrive.
     *
     * @param string $method HTTP method ('POST').
     * @param string $path API path (e.g., '/chat/completions').
     * @param array<string, mixed> $payload Request payload (should include 'stream' => true).
     * @return PsrResponseInterface The raw PSR-7 response with unconsumed body stream.
     *
     * @throws RateLimitException On HTTP 429.
     * @throws RequestTooLargeException On HTTP 413.
     * @throws ProviderOverloadedException On HTTP 503/529.
     * @throws DriverException On any other HTTP error.
     * @throws JsonException On JSON encoding failure.
     */
    protected function sendStreamRequest(string $method, string $path, array $payload): PsrResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $this->baseUrl . $path);

        // Add authentication and content-type headers
        foreach ($this->getAuthHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withHeader('Accept', 'text/event-stream');

        // Create and attach the JSON body
        $body = $this->streamFactory->createStream(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $request = $request->withBody($body);

        // Send the request via PSR-18
        $response = $this->httpClient->sendRequest($request);

        // Check for HTTP errors
        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        return $response;
    }

    /**
     * Maps HTTP error status codes to typed NexusAI exceptions.
     *
     * Status mapping:
     * - 429 → RateLimitException (with Retry-After header parsing)
     * - 413 → RequestTooLargeException
     * - 503, 529 → ProviderOverloadedException (with Retry-After)
     * - All others → DriverException::fromResponse() (auto-parses error JSON)
     *
     * @param PsrResponseInterface $response The error HTTP response.
     * @return never Always throws an exception.
     *
     * @throws RateLimitException
     * @throws RequestTooLargeException
     * @throws ProviderOverloadedException
     * @throws DriverException
     */
    protected function handleErrorResponse(PsrResponseInterface $response): never
    {
        $status = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $provider = $this->getProviderName();

        // Parse Retry-After header for rate limit and overload errors
        $retryAfterHeader = $response->getHeaderLine('Retry-After');
        $retryAfter = $retryAfterHeader !== '' ? (int) $retryAfterHeader : null;

        match ($status) {
            429 => throw new RateLimitException(
                message: "Rate limit exceeded for {$provider}",
                provider: $provider,
                retryAfter: $retryAfter,
                responseBody: $body,
            ),
            413 => throw new RequestTooLargeException(
                message: "Request too large for {$provider}",
                provider: $provider,
                responseBody: $body,
            ),
            503, 529 => throw new ProviderOverloadedException(
                message: "{$provider} is currently overloaded",
                provider: $provider,
                retryAfter: $retryAfter,
                responseBody: $body,
            ),
            default => throw DriverException::fromResponse($provider, $status, $body),
        };
    }

    /**
     * Returns the authentication headers for this provider.
     *
     * Default implementation returns a Bearer token. Subclasses can override
     * for different auth schemes (e.g., Anthropic uses 'x-api-key' header).
     *
     * @return array<string, string> Header name => value pairs.
     */
    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
    }
}
