# Observability

## EventBus

The `EventBus` propagates typed events to registered observers. Observer errors are silently caught — they never break the main request flow.

```php
use Token27\NexusAI\Observability\EventBus;
use Token27\NexusAI\Observability\Observer\MetricsObserver;

$bus = new EventBus();
$metrics = new MetricsObserver();
$bus->subscribe($metrics);
```

## MetricsObserver

Aggregates request statistics in memory:

```php
$snapshot = $metrics->getSnapshot();

echo $snapshot['total_requests'];    // int
echo $snapshot['total_tokens'];      // int
echo $snapshot['total_cost'];        // float (USD)
echo $snapshot['average_latency_ms']; // float
echo $snapshot['p95_latency_ms'];    // float — 95th percentile latency
echo $snapshot['error_rate'];        // float (0.0–1.0)
echo $snapshot['total_errors'];      // int
echo $snapshot['by_provider'];       // array — per-provider breakdown
```

## Custom Observer

```php
use Token27\NexusAI\Contract\ObserverInterface;
use Token27\NexusAI\Observability\Event\RequestCompleted;
use Token27\NexusAI\Observability\Event\RequestFailed;

class SlackAlertObserver implements ObserverInterface
{
    public function onEvent(string $event, object $source, mixed $data = null): void
    {
        if ($data instanceof RequestFailed) {
            $this->sendSlackAlert('AI request failed: ' . $data->exception->getMessage());
        }

        if ($data instanceof RequestCompleted && $data->pricingResult?->totalCostUsd() > 0.10) {
            $this->sendSlackAlert('Expensive request: $' . number_format($data->pricingResult->totalCostUsd(), 4));
        }
    }
}

$bus->subscribe(new SlackAlertObserver());
```

## Available Events

| Event String | Data Object | Fired When |
|---|---|---|
| `request.started` | `RequestStarted` | Before driver call |
| `request.completed` | `RequestCompleted` | Successful response |
| `request.failed` | `RequestFailed` | Exception thrown |

## Reset Metrics

```php
$metrics->reset(); // Clears all accumulated data
```

---

> **← Back:** [Pricing](pricing.md) · **Next:** [Testing →](testing.md)
