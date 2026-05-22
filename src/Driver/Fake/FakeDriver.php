<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Fake;

use Generator;
use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Contract\StreamChunkInterface;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;

/**
 * Testing driver that simulates AI responses without making HTTP requests.
 *
 * Provides two key testing capabilities:
 * 1. Response queuing: Pre-configure responses returned in FIFO order
 * 2. Request recording: Assert on captured requests after execution
 *
 * Supports both global response queuing and model-specific responses.
 *
 * Usage in tests:
 *   $fake = new FakeDriver();
 *   $fake->willReturn(new TextResponse(text: 'Hello!', finishReason: FinishReason::Stop));
 *
 *   NexusAI::registerDriver('openai', fn() => $fake);
 *   $response = NexusAI::using('openai', 'gpt-4o')->withPrompt('Hi')->asText();
 *
 *   $fake->assertRequestCount(1);
 *   $fake->assertPromptContains('Hi');
 *
 * Inspired by Prism's PrismFake but without Laravel Http::fake() dependency.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface
 */
final class FakeDriver implements DriverInterface
{
    /**
     * FIFO queue of pre-configured responses.
     *
     * @var array<ResponseInterface>
     */
    private array $responseQueue = [];

    /**
     * Model-specific response queues.
     *
     * @var array<string, array<ResponseInterface>>
     */
    private array $modelResponses = [];

    /**
     * All requests recorded in order.
     *
     * @var array<RequestInterface>
     */
    private array $recorded = [];

    /**
     * Stream chunks to yield when stream() is called.
     *
     * @var array<StreamChunkInterface>
     */
    private array $streamChunks = [];

    /**
     * Enqueues responses that will be returned in FIFO order.
     *
     * @param ResponseInterface ...$responses Responses to enqueue.
     * @return $this
     */
    public function willReturn(ResponseInterface ...$responses): self
    {
        foreach ($responses as $response) {
            $this->responseQueue[] = $response;
        }

        return $this;
    }

    /**
     * Enqueues responses specific to a model.
     *
     * When a request uses the given model, responses are dequeued from
     * the model-specific queue first.
     *
     * @param string $model Model identifier (e.g., 'gpt-4o').
     * @param ResponseInterface ...$responses Responses for this model.
     * @return $this
     */
    public function willReturnForModel(string $model, ResponseInterface ...$responses): self
    {
        if (!isset($this->modelResponses[$model])) {
            $this->modelResponses[$model] = [];
        }

        foreach ($responses as $response) {
            $this->modelResponses[$model][] = $response;
        }

        return $this;
    }

    /**
     * Sets stream chunks to yield when stream() is called.
     *
     * @param StreamChunkInterface ...$chunks Chunks to yield.
     * @return $this
     */
    public function willStream(StreamChunkInterface ...$chunks): self
    {
        foreach ($chunks as $chunk) {
            $this->streamChunks[] = $chunk;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Records the request and dequeues the next TextResponse.
     */
    public function text(TextRequest $request): TextResponse
    {
        $this->recorded[] = $request;
        $response = $this->dequeue($request->getModel());

        if (!$response instanceof TextResponse) {
            throw new \LogicException(sprintf(
                'FakeDriver expected TextResponse but got %s. Check your willReturn() calls.',
                $response::class,
            ));
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     *
     * Records the request and dequeues the next StructuredResponse.
     */
    public function structured(StructuredRequest $request): StructuredResponse
    {
        $this->recorded[] = $request;
        $response = $this->dequeue($request->getModel());

        if (!$response instanceof StructuredResponse) {
            throw new \LogicException(sprintf(
                'FakeDriver expected StructuredResponse but got %s. Check your willReturn() calls.',
                $response::class,
            ));
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     *
     * Records the request and dequeues the next EmbeddingResponse.
     */
    public function embeddings(EmbeddingRequest $request): EmbeddingResponse
    {
        $this->recorded[] = $request;
        $response = $this->dequeue($request->getModel());

        if (!$response instanceof EmbeddingResponse) {
            throw new \LogicException(sprintf(
                'FakeDriver expected EmbeddingResponse but got %s. Check your willReturn() calls.',
                $response::class,
            ));
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     *
     * Records the request and yields pre-configured stream chunks.
     *
     * @return Generator<int, StreamChunkInterface>
     */
    public function stream(TextRequest $request): Generator
    {
        $this->recorded[] = $request;

        foreach ($this->streamChunks as $chunk) {
            yield $chunk;
        }
    }

    /**
     * {@inheritdoc}
     *
     * FakeDriver supports all capabilities.
     */
    public function supports(string $capability): bool
    {
        return true;
    }

    // ── Assertion Methods ─────────────────────────────────────────────

    /**
     * Asserts that exactly N requests were recorded.
     *
     * @param int $expected Expected request count.
     * @throws \AssertionError If the count doesn't match.
     */
    public function assertRequestCount(int $expected): void
    {
        $actual = count($this->recorded);

        if ($actual !== $expected) {
            throw new \AssertionError(
                "Expected {$expected} requests, but {$actual} were recorded.",
            );
        }
    }

    /**
     * Asserts that at least one recorded request contains the given text in any message.
     *
     * Searches through all messages in all recorded requests.
     *
     * @param string $text Text to search for.
     * @throws \AssertionError If the text is not found in any request.
     */
    public function assertPromptContains(string $text): void
    {
        foreach ($this->recorded as $request) {
            foreach ($request->getMessages() as $message) {
                if (str_contains($message->getText(), $text)) {
                    return;
                }
            }
        }

        throw new \AssertionError(
            "No recorded request contains the text: '{$text}'.",
        );
    }

    /**
     * Asserts that at least one recorded request used the given model.
     *
     * @param string $model Model identifier to check.
     * @throws \AssertionError If no request used the model.
     */
    public function assertUsedModel(string $model): void
    {
        foreach ($this->recorded as $request) {
            if ($request->getModel() === $model) {
                return;
            }
        }

        throw new \AssertionError(
            "No recorded request used model: '{$model}'.",
        );
    }

    /**
     * Asserts that at least one recorded request had the given tool registered.
     *
     * @param string $name Tool name to check.
     * @throws \AssertionError If no request had the tool.
     */
    public function assertToolWasCalled(string $name): void
    {
        foreach ($this->recorded as $request) {
            if ($request instanceof TextRequest || $request instanceof StructuredRequest) {
                foreach ($request->tools as $tool) {
                    if ($tool->getName() === $name) {
                        return;
                    }
                }
            }
        }

        throw new \AssertionError(
            "No recorded request had tool: '{$name}'.",
        );
    }

    /**
     * Returns all recorded requests.
     *
     * @return array<RequestInterface> All recorded requests in order.
     */
    public function getRecorded(): array
    {
        return $this->recorded;
    }

    /**
     * Returns the last recorded request, or null if none.
     *
     * @return RequestInterface|null The last recorded request.
     */
    public function getLastRecorded(): ?RequestInterface
    {
        if ($this->recorded === []) {
            return null;
        }

        return $this->recorded[array_key_last($this->recorded)];
    }

    // ── Private Methods ───────────────────────────────────────────────

    /**
     * Dequeues the next response, checking model-specific queues first.
     *
     * @param string $model The model from the current request.
     * @return ResponseInterface The next response.
     *
     * @throws \LogicException If no responses are queued.
     */
    private function dequeue(string $model): ResponseInterface
    {
        // Check model-specific queue first
        if (isset($this->modelResponses[$model]) && $this->modelResponses[$model] !== []) {
            return array_shift($this->modelResponses[$model]);
        }

        // Fall back to global queue
        if ($this->responseQueue !== []) {
            return array_shift($this->responseQueue);
        }

        throw new \LogicException(
            "No responses queued in FakeDriver for model '{$model}'. "
            . 'Call willReturn() or willReturnForModel() before executing requests.',
        );
    }
}
