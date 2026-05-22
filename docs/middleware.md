# Middleware Pipeline

The middleware pipeline is the heart of NexusAI. Every request passes through a configurable stack before reaching the driver.

## Execution Model (Onion)

```
Request  →  [Retry]  →  [Cost]  →  [Cache]  →  Driver
Response ←  [Retry]  ←  [Cost]  ←  [Cache]  ←  Driver
```

## Global vs Per-Request

```php
// Global — applies to ALL requests
NexusAI::withMiddleware(new RetryMiddleware(maxRetries: 3));

// Per-request
$response = NexusAI::using('openai', 'gpt-4o')
    ->withMiddleware(new CacheMiddleware($cache))
    ->withPrompt('Hello')
    ->asText();
```

## Built-in Middlewares

### RetryMiddleware
Retries `RateLimitException` with exponential backoff + jitter.

```php
new RetryMiddleware(maxRetries: 3, baseDelayMs: 1000, multiplier: 2.0, jitter: true);
```

### CostTrackingMiddleware
Calculates the actual cost of each request and enforces an optional budget limit. Throws `CostLimitException` before sending if the estimated cost would exceed the budget.

`CostCalculator` requires a `PricingEngineInterface` instance from `token27/nexus-ai-pricing`:

```php
use Token27\NexusAI\Pipeline\Middleware\CostCalculator;
use Token27\NexusAI\Pipeline\Middleware\CostTrackingMiddleware;
use Token27\NexusAI\Pricing\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;

// Build engine with default price catalog (covers all major models)
$engine = PricingEngine::withTable(
    new ArrayPriceTable(DefaultPriceCatalog::get())
);
$calculator = new CostCalculator($engine);

// Add to pipeline with optional $5 budget limit
NexusAI::withMiddleware(
    new CostTrackingMiddleware(calculator: $calculator, budgetLimit: 5.00)
);

// After requests: check total spent this session
echo $calculator->getTotalSpent(); // float (USD)
```

After each successful request, `CostTrackingMiddleware` attaches a `PricingResultInterface` to the pipeline context. You can access it through the `RequestCompleted` observability event — see [Pricing →](pricing.md) for the full breakdown.

**Custom model prices at runtime:**
```php
$calculator->registerPrice('my-custom-model', inputPerMillion: 5.00, outputPerMillion: 20.00);
```

### CacheMiddleware
PSR-16 caching with short-circuit on hit. Skips streaming and tool requests.

```php
new CacheMiddleware(cache: $psrCache, ttl: 3600, prefix: 'nexus:');
```

### CircuitBreakerMiddleware
Opens after N failures, rejects requests during cooldown.

```php
new CircuitBreakerMiddleware(failureThreshold: 5, cooldownSeconds: 60);
```

### RateLimitMiddleware
Pre-checks rate limit headers before the next request.

```php
new RateLimitMiddleware(waitOnLimit: true); // false = throw immediately
```

### LoggingMiddleware / ValidationMiddleware

```php
new LoggingMiddleware(logger: $psrLogger);
new ValidationMiddleware();
```

## Custom Middleware

```php
use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Pipeline\Context;

class TimingMiddleware implements MiddlewareInterface
{
    public function process(Context $context, callable $next): Context
    {
        $start = microtime(true);
        $context = $next($context);
        echo sprintf("%.2fms\n", (microtime(true) - $start) * 1000);
        return $context;
    }
}
```

---

> **← Back:** [Chat History](chat-history.md) · **Next:** [Pricing →](pricing.md)
