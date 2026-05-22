# Pricing & Cost Tracking

NexusAI integrates with `token27/nexus-ai-pricing` to calculate the exact financial cost of every request, enforce spending budgets, and break down costs by provider, cache usage, and token type.

## Overview

The cost pipeline works in three steps:

1. **Before the request**: `CostTrackingMiddleware` estimates the cost from the prompt text and checks it against the budget limit.
2. **After the request**: Actual cost is calculated from the `Usage` returned by the driver (exact token counts).
3. **Result attached**: A `PricingResultInterface` is attached to the pipeline context and emitted via the `RequestCompleted` observability event.

## Quick Start

```php
use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pipeline\Middleware\CostCalculator;
use Token27\NexusAI\Pipeline\Middleware\CostTrackingMiddleware;
use Token27\NexusAI\Pricing\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;

// 1. Build a pricing engine with the default catalog
$engine = PricingEngine::withTable(
    new ArrayPriceTable(DefaultPriceCatalog::get())
);

// 2. Wrap it in a CostCalculator
$calculator = new CostCalculator($engine);

// 3. Add to the global pipeline (optionally with a budget limit)
NexusAI::withMiddleware(
    new CostTrackingMiddleware(calculator: $calculator, budgetLimit: 10.00)
);

// 4. Make requests as normal — cost tracking is automatic
$response = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt('Explain dependency injection.')
    ->asText();

// 5. Check session total
echo 'Spent: $' . number_format($calculator->getTotalSpent(), 6); // e.g. $0.000230
```

## Pricing Engine Setup

### Default Price Catalog

The default catalog includes pricing for all major models from OpenAI, Anthropic, Google Gemini, DeepSeek, Mistral, and more:

```php
use Token27\NexusAI\Pricing\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;

$engine = PricingEngine::withTable(
    new ArrayPriceTable(DefaultPriceCatalog::get())
);
```

### Custom Price Table

Build your own table for internal models, enterprise pricing, or models not in the default catalog:

```php
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PricingEngine;

$engine = PricingEngine::withTable(new ArrayPriceTable([
    new ModelPrice('gpt-4o',           inputPerMillion: 2.50,  outputPerMillion: 10.00),
    new ModelPrice('gpt-4o-mini',      inputPerMillion: 0.15,  outputPerMillion: 0.60),
    new ModelPrice('claude-sonnet-4-6', inputPerMillion: 3.00,  outputPerMillion: 15.00),
    new ModelPrice('my-custom-model',  inputPerMillion: 5.00,  outputPerMillion: 20.00),
]));
```

### Register Prices at Runtime

Add or update prices after the engine is created:

```php
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

// Via CostCalculator (convenience wrapper)
$calculator->registerPrice('new-model', inputPerMillion: 4.00, outputPerMillion: 16.00);

// Or directly on the engine
$engine->registerPrice(new ModelPrice('new-model', inputPerMillion: 4.00, outputPerMillion: 16.00));
```

## Budget Limits

When `budgetLimit` is set, `CostTrackingMiddleware` estimates the cost of each request *before* sending it. If `totalSpent + estimatedCost > budgetLimit`, a `CostLimitException` is thrown before any API call is made.

```php
use Token27\NexusAI\Exception\CostLimitException;

try {
    $response = NexusAI::using('openai', 'gpt-4o')
        ->withPrompt('Write a 10,000-word essay...')
        ->asText();
} catch (CostLimitException $e) {
    echo $e->getMessage();
    // "Budget limit exceeded: $4.9800 spent + $0.0450 estimated > $5.0000 limit"

    echo $e->budgetLimit;    // 5.0
    echo $e->totalSpent;     // 4.98
    echo $e->estimatedCost;  // 0.045
}
```

### Reset Spending Between Sessions

```php
// Reset accumulated spending for a new session or job
$calculator->addSpent(-$calculator->getTotalSpent());

// Or track manually
$spent = $calculator->getTotalSpent();
```

## Per-Request Engine Override

Override the pricing engine for a single request — useful for A/B testing different price models or applying negotiated enterprise rates without affecting the global calculator:

```php
use Token27\NexusAI\Pricing\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

$enterpriseEngine = PricingEngine::withTable(new ArrayPriceTable([
    // Enterprise negotiated rate — 30% discount
    new ModelPrice('gpt-4o', inputPerMillion: 1.75, outputPerMillion: 7.00),
]));

$response = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt('...')
    ->withPricingEngine($enterpriseEngine)  // Only this request uses enterprise pricing
    ->asText();
```

> **Note:** Spending accumulation always flows to the **global** `$calculator`, regardless of the per-request override. The override only affects which prices are used for *calculation* — not where the spending total is tracked.

## Accessing Pricing Results

### From the Observability EventBus

The most natural way to read pricing data is via the `RequestCompleted` event:

```php
use Token27\NexusAI\Contract\ObserverInterface;
use Token27\NexusAI\Observability\Event\RequestCompleted;

class CostLogObserver implements ObserverInterface
{
    public function onEvent(string $event, object $source, mixed $data = null): void
    {
        if (!($data instanceof RequestCompleted) || $data->pricingResult === null) {
            return;
        }

        $result = $data->pricingResult;
        echo sprintf(
            "[%s/%s] Input: %d tokens ($%.6f) | Output: %d tokens ($%.6f) | Total: $%.6f\n",
            $data->request->getProvider(),
            $data->request->getModel(),
            $result->inputTokens(),
            $result->inputCostUsd(),
            $result->outputTokens(),
            $result->outputCostUsd(),
            $result->totalCostUsd(),
        );
    }
}
```

### From MetricsObserver

`MetricsObserver` accumulates totals automatically:

```php
$snap = $metrics->getSnapshot();
echo $snap['total_cost'];    // float (USD) — accumulated across all requests
echo $snap['total_tokens'];  // int — total tokens consumed
```

## Anthropic Cache Tokens

Anthropic's prompt caching uses additive billing: cache write and cache read tokens are *separate* from regular input tokens. Pass them in `Usage` and the pricing engine handles the math automatically:

```php
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

// Anthropic model price with cache rates
$price = new ModelPrice(
    model: 'claude-sonnet-4-6',
    inputPerMillion:      3.00,
    outputPerMillion:    15.00,
    cacheWritePerMillion: 3.75,  // 25% surcharge vs input
    cacheReadPerMillion:  0.30,  // 90% discount vs input
);
```

When the driver returns cache tokens in `Usage`, `CostCalculator` passes them through:

```php
// Usage from driver response (set by Anthropic driver automatically)
$usage = new Usage(
    promptTokens: 1000,
    completionTokens: 200,
    cacheWriteTokens: 500,   // tokens written to cache
    cacheReadTokens: 800,    // tokens read from cache
);

// Effective cost:
// input:       1000 / 1M * $3.00  = $0.003000
// output:       200 / 1M * $15.00 = $0.003000
// cacheWrite:   500 / 1M * $3.75  = $0.001875
// cacheRead:    800 / 1M * $0.30  = $0.000240
// total = $0.008115
```

## OpenAI Cache Tokens (Subset Model)

OpenAI's cache billing is different: cached read tokens are *already counted within* `promptTokens`. The pricing engine adjusts the cost to avoid double-counting:

```php
$price = new ModelPrice(
    model: 'gpt-4o',
    inputPerMillion:    2.50,
    outputPerMillion:  10.00,
    cacheReadPerMillion: 1.25,  // 50% discount vs input
    cacheReadIsSubsetOfInput: true,  // OpenAI-style: reads are subset of input
);

// If promptTokens=1000 and cacheReadTokens=600:
// Regular input: (1000 - 600) = 400 tokens at $2.50/M = $0.001000
// Cached input:   600 tokens at $1.25/M = $0.000750
// total input = $0.001750 (vs $0.002500 without cache)
```

## PricingResultInterface Reference

`$context->getPricingResult()` (and `$data->pricingResult` in observers) returns a `PricingResultInterface` with these methods:

| Method | Returns | Description |
|--------|---------|-------------|
| `totalCostUsd()` | `float` | Total request cost in USD |
| `inputCostUsd()` | `float` | Cost for input/prompt tokens |
| `outputCostUsd()` | `float` | Cost for output/completion tokens |
| `cacheWriteCostUsd()` | `float` | Cost for cache write tokens |
| `cacheReadCostUsd()` | `float` | Cost for cache read tokens |
| `cacheSavingsUsd()` | `float` | Savings vs non-cached pricing |
| `inputTokens()` | `int` | Input token count |
| `outputTokens()` | `int` | Output token count |
| `cacheWriteTokens()` | `int` | Cache write token count |
| `cacheReadTokens()` | `int` | Cache read token count |
| `isUnknownModel()` | `bool` | `true` if model has no price entry |
| `isZero()` | `bool` | `true` if total cost is $0.00 |
| `model()` | `string` | The model identifier |
| `currency()` | `string` | Currency code (always `"USD"`) |
| `format()` | `string` | Formatted total: `"$0.002300"` |
| `formatDetailed()` | `string` | Full breakdown string |
| `toArray()` | `array` | All fields as associative array |
| `add(PricingResultInterface)` | `PricingResultInterface` | Sum two results together |

### Example: Full Cost Breakdown

```php
$result = $data->pricingResult;

if ($result->isUnknownModel()) {
    echo "No price data for model: " . $result->model();
} else {
    echo $result->format();          // "$0.002300"
    echo $result->formatDetailed();  // "in: $0.001500, out: $0.000800"

    $arr = $result->toArray();
    // [
    //   'model'              => 'gpt-4o',
    //   'input_tokens'       => 600,
    //   'output_tokens'      => 80,
    //   'total_cost_usd'     => 0.0023,
    //   'input_cost_usd'     => 0.0015,
    //   'output_cost_usd'    => 0.0008,
    //   'cache_write_tokens' => 0,
    //   'cache_read_tokens'  => 0,
    //   ...
    // ]
}
```

## Creating a PricingResult Manually

Useful in tests or offline calculations:

```php
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;

$result = PricingResult::compute(
    new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
    inputTokens:  1_000,
    outputTokens:   500,
);
// total = 1000/1M * 2.50 + 500/1M * 10.00 = $0.002500 + $0.005000 = $0.007500

// For unknown models:
$unknown = PricingResult::unknown('my-unknown-model');
$unknown->isUnknownModel(); // true
$unknown->totalCostUsd();   // 0.0
```

## Aggregating Costs Across Requests

```php
// Sum multiple results
$total = $result1->add($result2)->add($result3);
echo $total->totalCostUsd(); // combined total

// Or use MetricsObserver for automatic aggregation
$snap = $metricsObserver->getSnapshot();
echo $snap['by_provider']['openai']['cost'];  // per-provider cost total
```

---

> **← Back:** [Middleware Pipeline](middleware.md) · **Next:** [Observability →](observability.md)
