<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\Contract\UsageInterface;

/**
 * Immutable object that travels through the entire middleware pipeline.
 *
 * Contains the request, response (when available), timing, costs, and
 * arbitrary metadata. Every with*() method returns a NEW instance via clone,
 * preventing bugs where one middleware modifies state another doesn't expect.
 *
 * Unlike Neuron AI's mutable State, Context follows PSR-7's immutability pattern.
 *
 * @see \Token27\NexusAI\Pipeline\Pipeline
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 */
final class Context
{
    /** @var ResponseInterface|null Response from the driver, null until driver executes. */
    private ?ResponseInterface $response;

    /** @var UsageInterface|null Token usage extracted from the response. */
    private ?UsageInterface $usage;

    /** @var PricingResultInterface|null Pricing result calculated by CostTrackingMiddleware. */
    private ?PricingResultInterface $pricingResult;

    /** @var array<string, mixed> Free key-value store for middleware data sharing. */
    private array $metadata;

    /**
     * @param RequestInterface $request The original request (never changes).
     * @param float $startTime Timestamp for latency measurement.
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly float $startTime = 0.0,
    ) {
        $this->response = null;
        $this->usage = null;
        $this->pricingResult = null;
        $this->metadata = [];

        // If no startTime provided, use current time
        if ($this->startTime === 0.0) {
            // We can't reassign readonly, so we handle this via a named constructor
        }
    }

    /**
     * Creates a new Context with the current microtime as start time.
     *
     * @param RequestInterface $request The request to process.
     */
    public static function create(RequestInterface $request): self
    {
        return new self($request, microtime(true));
    }

    /**
     * Returns the original request.
     *
     * @return RequestInterface The immutable request.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Returns the response, or null if the driver hasn't executed yet.
     *
     * @return ResponseInterface|null The response.
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Returns the token usage, or null if not yet available.
     *
     * @return UsageInterface|null The usage data.
     */
    public function getUsage(): ?UsageInterface
    {
        return $this->usage;
    }

    /**
     * Returns the pricing result, or null if not yet calculated.
     *
     * @return PricingResultInterface|null The full pricing breakdown.
     */
    public function getPricingResult(): ?PricingResultInterface
    {
        return $this->pricingResult;
    }

    /**
     * Returns elapsed time in milliseconds since context creation.
     *
     * @return float Milliseconds elapsed.
     */
    public function getElapsedMs(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    /**
     * Returns a metadata value by key.
     *
     * @param string $key The metadata key.
     * @param mixed $default Default value if key doesn't exist.
     * @return mixed The metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Returns all metadata.
     *
     * @return array<string, mixed> All metadata key-value pairs.
     */
    public function getAllMeta(): array
    {
        return $this->metadata;
    }

    /**
     * Returns a NEW Context with the given response set.
     *
     * @param ResponseInterface $response The response to set.
     * @return self A new Context instance.
     */
    public function withResponse(ResponseInterface $response): self
    {
        $clone = clone $this;
        $clone->response = $response;
        return $clone;
    }

    /**
     * Returns a NEW Context with the given usage set.
     *
     * @param UsageInterface $usage The usage to set.
     * @return self A new Context instance.
     */
    public function withUsage(UsageInterface $usage): self
    {
        $clone = clone $this;
        $clone->usage = $usage;
        return $clone;
    }

    /**
     * Returns a NEW Context with the given pricing result set.
     *
     * @param PricingResultInterface $result The pricing result to set.
     * @return self A new Context instance.
     */
    public function withPricingResult(PricingResultInterface $result): self
    {
        $clone = clone $this;
        $clone->pricingResult = $result;
        return $clone;
    }

    /**
     * Returns a NEW Context with a metadata value added/updated.
     *
     * @param string $key The metadata key.
     * @param mixed $value The metadata value.
     * @return self A new Context instance.
     */
    public function withMeta(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;
        return $clone;
    }
}
