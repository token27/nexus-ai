<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Observer;

use Token27\NexusAI\Contract\ObserverInterface;
use Token27\NexusAI\Observability\Event\RequestCompleted;
use Token27\NexusAI\Observability\Event\RequestFailed;

/**
 * Observer that accumulates in-memory metrics for monitoring and dashboarding.
 *
 * Tracks total requests, tokens, cost, errors, and latency distributions.
 * Provides query methods for averages, percentiles (P50/P95/P99), and
 * per-provider breakdowns.
 *
 * All data is held in memory — no external dependencies required.
 *
 * @see \Token27\NexusAI\Contract\ObserverInterface
 * @see \Token27\NexusAI\Observability\EventBus
 */
final class MetricsObserver implements ObserverInterface
{
    /** @var int Total requests processed. */
    private int $totalRequests = 0;

    /** @var int Total tokens consumed across all requests. */
    private int $totalTokens = 0;

    /** @var float Total cost in USD across all requests. */
    private float $totalCost = 0.0;

    /** @var int Total failed requests. */
    private int $totalErrors = 0;

    /** @var array<float> Latencies of each request in milliseconds. */
    private array $latencies = [];

    /**
     * Per-provider metrics breakdown.
     *
     * @var array<string, array{requests: int, tokens: int, cost: float, errors: int, latencies: array<float>}>
     */
    private array $byProvider = [];

    /**
     * Receives a system event and accumulates metrics.
     *
     * Only processes RequestCompleted and RequestFailed events.
     *
     * @param string $event The event name.
     * @param object $source The object that emitted the event.
     * @param mixed $data Optional event data DTO.
     */
    public function onEvent(string $event, object $source, mixed $data = null): void
    {
        if (!is_object($data)) {
            return;
        }

        match ($data::class) {
            RequestCompleted::class => $this->handleCompleted($data),
            RequestFailed::class => $this->handleFailed($data),
            default => null,
        };
    }

    /**
     * Returns a snapshot of all accumulated metrics.
     *
     * @return array<string, mixed> All metrics as an associative array.
     */
    public function getSnapshot(): array
    {
        return [
            'total_requests' => $this->totalRequests,
            'total_tokens' => $this->totalTokens,
            'total_cost' => $this->totalCost,
            'total_errors' => $this->totalErrors,
            'average_latency_ms' => $this->getAverageLatencyMs(),
            'p95_latency_ms' => $this->getP95LatencyMs(),
            'error_rate' => $this->getErrorRate(),
            'by_provider' => $this->byProvider,
        ];
    }

    /**
     * Returns the average latency across all requests.
     *
     * @return float Average latency in milliseconds, or 0.0 if no requests.
     */
    public function getAverageLatencyMs(): float
    {
        if (empty($this->latencies)) {
            return 0.0;
        }

        return array_sum($this->latencies) / count($this->latencies);
    }

    /**
     * Returns the 95th percentile latency.
     *
     * @return float P95 latency in milliseconds, or 0.0 if no requests.
     */
    public function getP95LatencyMs(): float
    {
        return $this->getPercentile(95.0);
    }

    /**
     * Returns the 50th percentile (median) latency.
     *
     * @return float P50 latency in milliseconds, or 0.0 if no requests.
     */
    public function getP50LatencyMs(): float
    {
        return $this->getPercentile(50.0);
    }

    /**
     * Returns the 99th percentile latency.
     *
     * @return float P99 latency in milliseconds, or 0.0 if no requests.
     */
    public function getP99LatencyMs(): float
    {
        return $this->getPercentile(99.0);
    }

    /**
     * Returns the total accumulated cost.
     *
     * @return float Total cost in USD.
     */
    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    /**
     * Returns the error rate (errors / total requests).
     *
     * @return float Error rate between 0.0 and 1.0, or 0.0 if no requests.
     */
    public function getErrorRate(): float
    {
        if ($this->totalRequests === 0) {
            return 0.0;
        }

        return $this->totalErrors / $this->totalRequests;
    }

    /**
     * Resets all accumulated metrics to zero.
     */
    public function reset(): void
    {
        $this->totalRequests = 0;
        $this->totalTokens = 0;
        $this->totalCost = 0.0;
        $this->totalErrors = 0;
        $this->latencies = [];
        $this->byProvider = [];
    }

    /**
     * Handles a RequestCompleted event by accumulating success metrics.
     *
     * @param RequestCompleted $data The completion event data.
     */
    private function handleCompleted(RequestCompleted $data): void
    {
        $provider = $data->request->getProvider();

        $this->totalRequests++;
        $this->latencies[] = $data->elapsedMs;

        if ($data->usage !== null) {
            $this->totalTokens += $data->usage->totalTokens();
        }

        if ($data->pricingResult !== null) {
            $this->totalCost += $data->pricingResult->totalCostUsd();
        }

        $current = $this->byProvider[$provider] ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0.0, 'errors' => 0, 'latencies' => []];
        $this->byProvider[$provider] = [
            'requests' => $current['requests'] + 1,
            'tokens' => $current['tokens'] + ($data->usage?->totalTokens() ?? 0),
            'cost' => $current['cost'] + ($data->pricingResult?->totalCostUsd() ?? 0.0),
            'errors' => $current['errors'],
            'latencies' => array_merge($current['latencies'], [$data->elapsedMs]),
        ];
    }

    /**
     * Handles a RequestFailed event by accumulating error metrics.
     *
     * @param RequestFailed $data The failure event data.
     */
    private function handleFailed(RequestFailed $data): void
    {
        $provider = $data->request->getProvider();

        $this->totalRequests++;
        $this->totalErrors++;
        $this->latencies[] = $data->elapsedMs;

        $current = $this->byProvider[$provider] ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0.0, 'errors' => 0, 'latencies' => []];
        $this->byProvider[$provider] = [
            'requests' => $current['requests'] + 1,
            'tokens' => $current['tokens'],
            'cost' => $current['cost'],
            'errors' => $current['errors'] + 1,
            'latencies' => array_merge($current['latencies'], [$data->elapsedMs]),
        ];
    }

    /**
     * Calculates a percentile value from the latencies array.
     *
     * @param float $percentile The percentile to calculate (0-100).
     * @return float The percentile value in milliseconds, or 0.0 if no data.
     */
    private function getPercentile(float $percentile): float
    {
        if (empty($this->latencies)) {
            return 0.0;
        }

        $sorted = $this->latencies;
        sort($sorted);

        $count = count($sorted);
        $index = ($percentile / 100.0) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        if ($lower === $upper || $upper >= $count) {
            return $sorted[$lower];
        }

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $fraction;
    }
}
