# Pricing & Cost Flow

Visual reference for how cost calculation works at runtime: the `CostTrackingMiddleware` lifecycle, pre-request estimation with and without a tokenizer, budget enforcement, and how Anthropic vs OpenAI handle cache token billing differently.

For static architecture context, see [Architecture →](architecture.md). For the request lifecycle, see [Request Lifecycle →](request-lifecycle.md).

---

## CostTrackingMiddleware Full Lifecycle

`CostTrackingMiddleware` runs in two phases: **BEFORE** (estimation + budget guard) and **AFTER** (actual cost calculation + spending accumulation). The `PricingResult` is attached to `Context` and flows to the `RequestCompleted` observability event.

```mermaid
sequenceDiagram
    participant CTX as Context
    participant COST as CostTrackingMiddleware
    participant CALC as CostCalculator
    participant ENGINE as PricingEngine
    participant NEXT as Next Middleware / Driver
    participant OBS as Observability (optional)

    Note over COST: ── BEFORE PHASE ──

    COST->>CTX: getMeta('_pricing_engine')
    alt Per-request engine override set
        CTX-->>COST: PricingEngineInterface (custom engine)
        Note over COST: Create temporary CostCalculator\nfor this request only
    else No override
        CTX-->>COST: null → use global $this->calculator
    end

    alt Budget limit configured
        COST->>CALC: estimate(request)
        CALC->>CALC: extract text from all messages
        CALC->>ENGINE: estimate(text, model)
        ENGINE-->>CALC: float (estimated input cost)
        CALC-->>COST: float

        COST->>CALC: getTotalSpent()
        CALC-->>COST: float (accumulated session spending)

        Note over COST: Check: totalSpent + estimated > budgetLimit?

        alt Budget would be exceeded
            COST-->>Caller: throw CostLimitException\n{budgetLimit, totalSpent, estimatedCost}
        end
    end

    Note over COST: ── EXECUTE PHASE ──

    COST->>NEXT: next(context)
    Note over NEXT: Pipeline continues: Cache → Driver → API
    NEXT-->>COST: Context{response, usage}

    Note over COST: ── AFTER PHASE ──

    COST->>CALC: calculate(model, usage)
    Note over CALC: usage has: promptTokens, completionTokens,\ncacheWriteTokens, cacheReadTokens

    CALC->>ENGINE: calculate(model, inputTokens, outputTokens,\ncacheWriteTokens, cacheReadTokens)
    ENGINE->>ENGINE: Look up ModelPrice for model
    ENGINE->>ENGINE: Compute: input cost + output cost + cache costs
    ENGINE-->>CALC: PricingResultInterface

    CALC-->>COST: PricingResultInterface

    COST->>CALC: addSpent(result.totalCostUsd())
    Note over CALC: Accumulate in session total\n(always goes to global $this->calculator,\neven when per-request override was used)

    COST->>CTX: withPricingResult(result)
    CTX-->>COST: new Context (immutable clone)

    COST-->>Caller: Context{response, usage, pricingResult}
    Note over OBS: RequestCompleted event\nincludes pricingResult
```

---

## Pre-Request Cost Estimation Decision Tree

Before sending a request, `CostCalculator::estimate()` tries to get an accurate token count. If `nexus-ai-tokenizer` is **not** installed, it falls back to a character-based approximation (`mb_strlen / 4`).

```mermaid
flowchart TD
    START["CostCalculator::estimate(request)"]

    START --> EXTRACT["Extract text: concatenate all message texts"]
    EXTRACT --> MODEL["Get model identifier from request"]
    MODEL --> CALL["PricingEngine::estimate(text, model)"]

    CALL --> HAS_TOK{TextEstimatorInterface\nconfigured in engine?}

    HAS_TOK -- YES\nnexus-ai-tokenizer installed --> TOKENG["TokenizerEngine::for(model)"]
    TOKENG --> STRATEGY{Model family?}
    STRATEGY -- "GPT-3.5/4/4o" --> TIKTOKEN["TiktokenStrategy\ncl100k_base / o200k_base\nBPE tokenizer"]
    STRATEGY -- "Claude / Gemini" --> BPE["BpeStrategy\nHuggingFace vocab\n±5% approximation"]
    STRATEGY -- "Unknown model" --> CHARFB["CharDivisionStrategy\nmb_strlen / 4"]

    TIKTOKEN --> TOKENS_ACC["Accurate token count\n±2% error"]
    BPE --> TOKENS_APP["Near-accurate count\n±5% error"]
    CHARFB --> TOKENS_ROUGH["Rough estimate\n±40% error"]

    HAS_TOK -- NO\nnexus-ai-tokenizer not installed --> THROWS["throws EstimationNotAvailableException"]
    THROWS --> FALLBACK["FALLBACK (in CostCalculator):\nmb_strlen(text) / 4\n≈ char-based token estimate\n±40% error"]
    FALLBACK --> TOKENS_ROUGH

    TOKENS_ACC --> PRICE["Get ModelPrice for model\n(inputPerMillion)"]
    TOKENS_APP --> PRICE
    TOKENS_ROUGH --> PRICE

    PRICE --> COST_USD["estimatedCost = tokens / 1_000_000 × inputPerMillion"]
    COST_USD --> RESULT["Return float (USD)"]

    style TOKENS_ACC fill:#99ff99
    style TOKENS_APP fill:#ffffaa
    style TOKENS_ROUGH fill:#ffccaa
    style FALLBACK fill:#ffddaa
```

---

## Budget Enforcement Decision Tree

```mermaid
flowchart TD
    CTX_IN["Context enters CostTrackingMiddleware"]
    CTX_IN --> HAS_BUDGET{budgetLimit\nconfigured?}

    HAS_BUDGET -- No --> EXECUTE["Execute pipeline (no check)"]

    HAS_BUDGET -- Yes --> ESTIMATE["estimate(request)"]
    ESTIMATE --> SPENT["getTotalSpent()"]
    SPENT --> CHECK{totalSpent + estimated\n> budgetLimit?}

    CHECK -- No --> EXECUTE

    CHECK -- Yes --> THROW{{"throw CostLimitException\n\nbudgetLimit: $5.00\ntotalSpent: $4.98\nestimatedCost: $0.045\n\n'$4.9800 + $0.0450 > $5.0000'"}}

    EXECUTE --> ACTUAL["After response:\ncalculate actual cost"]
    ACTUAL --> ACCUM["addSpent(actualCost)"]
    ACCUM --> ATTACH["Attach PricingResult to Context"]
    ATTACH --> CTX_OUT["Context flows to next middleware"]

    style THROW fill:#ff9999
```

---

## Anthropic Cache Token Billing (Additive Model)

Anthropic's prompt caching uses **additive** billing: cache write and cache read tokens are billed **in addition** to regular input tokens. They each have separate per-million rates.

```mermaid
flowchart LR
    subgraph USAGE ["Usage returned by Anthropic Driver"]
        UT["promptTokens: 1000\ncompletionTokens: 200\ncacheWriteTokens: 500\ncacheReadTokens: 800"]
    end

    subgraph PRICE ["ModelPrice (claude-sonnet-4-6)"]
        PT["inputPerMillion: $3.00\noutputPerMillion: $15.00\ncacheWritePerMillion: $3.75\ncacheReadPerMillion: $0.30\ncacheReadIsSubsetOfInput: false"]
    end

    subgraph CALC ["Cost Calculation (Additive)"]
        direction TB
        C1["input:      1000 / 1M × $3.00  = $0.003000"]
        C2["output:      200 / 1M × $15.00 = $0.003000"]
        C3["cacheWrite:  500 / 1M × $3.75  = $0.001875"]
        C4["cacheRead:   800 / 1M × $0.30  = $0.000240"]
        TOTAL["TOTAL: $0.008115"]
    end

    subgraph SAVINGS ["vs No Cache"]
        NC["Without cache:\n2300 input tokens × $3.00/M = $0.006900\n+ 200 output  = $0.003000\nTotal: $0.009900"]
        SAV["Cache savings: $0.001785\n(18% reduction)"]
    end

    USAGE --> CALC
    PRICE --> CALC
    C1 --> TOTAL
    C2 --> TOTAL
    C3 --> TOTAL
    C4 --> TOTAL
    CALC --> SAVINGS
```

---

## OpenAI Cache Token Billing (Subset Model)

OpenAI's prompt caching uses a **subset** model: cached tokens are **already included** within `promptTokens`. The pricing engine avoids double-counting by substituting the cached portion at the lower cached rate.

```mermaid
flowchart LR
    subgraph USAGE ["Usage returned by OpenAI Driver"]
        UT["promptTokens: 1000\ncompletionTokens: 200\ncacheWriteTokens: 0\ncacheReadTokens: 600\n\n(600 of the 1000 prompt tokens were cached)"]
    end

    subgraph PRICE ["ModelPrice (gpt-4o)"]
        PT["inputPerMillion: $2.50\noutputPerMillion: $10.00\ncacheReadPerMillion: $1.25\ncacheReadIsSubsetOfInput: true"]
    end

    subgraph CALC ["Cost Calculation (Subset)"]
        direction TB
        C1["regularInput: (1000 - 600) = 400 tokens × $2.50/M = $0.001000"]
        C2["cachedInput:   600 tokens × $1.25/M = $0.000750"]
        C3["output:        200 tokens × $10.00/M = $0.002000"]
        TOTAL["TOTAL: $0.003750"]
    end

    subgraph SAVINGS ["vs No Cache"]
        NC["Without cache:\n1000 input × $2.50/M = $0.002500\n+ 200 output = $0.002000\nTotal: $0.004500"]
        SAV["Cache savings: $0.000750\n(17% reduction)"]
    end

    USAGE --> CALC
    PRICE --> CALC
    C1 --> TOTAL
    C2 --> TOTAL
    C3 --> TOTAL
    CALC --> SAVINGS
```

---

## Billing Models Comparison

```mermaid
flowchart TB
    subgraph ANTHROPIC ["Anthropic — Additive"]
        direction LR
        A_INPUT["Regular input tokens\nbilled at inputPerMillion"]
        A_WRITE["Cache WRITE tokens\nbilled at cacheWritePerMillion\n(separate from input)"]
        A_READ["Cache READ tokens\nbilled at cacheReadPerMillion\n(separate from input)"]
        A_OUTPUT["Output tokens\nbilled at outputPerMillion"]
    end

    subgraph OPENAI ["OpenAI — Subset"]
        direction LR
        O_REGULAR["(promptTokens - cacheReadTokens)\nbilled at full inputPerMillion"]
        O_CACHED["cacheReadTokens\nbilled at cacheReadPerMillion\n(already within promptTokens)"]
        O_OUTPUT["Output tokens\nbilled at outputPerMillion"]
    end

    KEY["Key difference:\nAnthropic: all 4 token types are separate additive costs\nOpenAI: cached tokens are a subset of input tokens, billed at a discount"]

    style KEY fill:#e0e0ff
```

---

## PricingResult Data Flow Through the System

How a single `PricingResultInterface` object travels from calculation to every consumer:

```mermaid
flowchart TD
    DRV["Driver returns TextResponse{usage}"]
    DRV --> COST_CALC["CostTrackingMiddleware\ncalculate(model, usage)"]
    COST_CALC --> RESULT["PricingResultInterface\n{totalCostUsd, inputCostUsd, outputCostUsd,\ncacheWriteCostUsd, cacheReadCostUsd,\ninputTokens, outputTokens, model, ...}"]

    RESULT --> CTX["Context::withPricingResult(result)\n→ new Context clone"]
    RESULT --> ACCUM["CostCalculator::addSpent(result.totalCostUsd)\n→ session total accumulation"]

    CTX --> LOGMW["LoggingMiddleware\npricingResult->totalCostUsd()\nwritten to PSR-3 log"]
    CTX --> RESP_RETURN["Context returned to PendingRequest\n(result available to observability middleware)"]
    CTX --> OBS_MW["ObservabilityMiddleware\nRequestCompleted{pricingResult}"]

    OBS_MW --> EBUS["EventBus::emit('request.completed')"]
    EBUS --> METRICS["MetricsObserver\ntotalCost += result.totalCostUsd()\nbyProvider[provider]['cost'] += ..."]
    EBUS --> LOGOBS["LogObserver\nlog pricingResult details"]
    EBUS --> CUSTOM["Custom Observers\ne.g. alert if cost > $0.10\ne.g. write to analytics DB"]

    ACCUM --> SPENT["CostCalculator::getTotalSpent()\nSession-level total\nused for budget checks on next request"]

    style RESULT fill:#99ccff
```

---

## Per-Request Engine Override Flow

When `->withPricingEngine($customEngine)` is called, the custom engine is stored in context metadata and read by `CostTrackingMiddleware::resolveCalculator()`:

```mermaid
sequenceDiagram
    actor User as User Code
    participant PR as PendingRequest
    participant CTX as Context
    participant COST as CostTrackingMiddleware
    participant GLOBAL as Global CostCalculator
    participant CUSTOM as Temporary CostCalculator (custom engine)

    User->>PR: ->withPricingEngine($enterpriseEngine)
    PR->>CTX: context->withMeta('_pricing_engine', $enterpriseEngine)
    Note over CTX: Custom engine stored in metadata

    PR->>COST: pipeline runs → cost middleware called

    COST->>CTX: getMeta('_pricing_engine')
    CTX-->>COST: $enterpriseEngine (not null)

    COST->>CUSTOM: new CostCalculator($enterpriseEngine)
    Note over CUSTOM: Temporary calculator for this request

    Note over COST: estimate() and calculate() use CUSTOM engine
    Note over COST: (enterprise prices apply)

    COST->>CUSTOM: calculate(model, usage)
    CUSTOM-->>COST: PricingResult (at enterprise rates)

    COST->>GLOBAL: addSpent(result.totalCostUsd())
    Note over GLOBAL: Spending ALWAYS accumulates to global calculator\nregardless of per-request override

    Note over COST: CUSTOM is discarded after this request
```

---

## Tokenizer Integration (Future Enhancement)

When `token27/nexus-ai-tokenizer` is installed and configured in `PricingEngine`, pre-request estimation becomes significantly more accurate. This diagram shows the extended flow:

```mermaid
sequenceDiagram
    participant CALC as CostCalculator
    participant ENGINE as PricingEngine
    participant EST as TextEstimatorInterface
    participant TOK as TokenizerEngine
    participant STRAT as BpeStrategy / TiktokenStrategy

    CALC->>CALC: estimate(request): extract all message text
    CALC->>ENGINE: estimate(text, 'gpt-4o')

    ENGINE->>EST: estimator->countTokens(text, 'gpt-4o')
    EST->>TOK: TokenizerEngine::for('gpt-4o')
    TOK->>TOK: resolve strategy: o200k_base (GPT-4o family)
    TOK->>STRAT: count(text)

    Note over STRAT: BPE tokenization:\n1. Byte-pair encoding vocab lookup\n2. Count actual subword tokens\n3. Same algorithm as OpenAI tiktoken

    STRAT-->>TOK: 342 tokens
    TOK-->>EST: 342 tokens
    EST-->>ENGINE: 342 tokens

    ENGINE->>ENGINE: price = getPriceFor('gpt-4o')
    ENGINE->>ENGINE: cost = 342 / 1_000_000 × price.inputPerMillion
    ENGINE-->>CALC: PricingResult{totalCostUsd: 0.000855}

    Note over CALC: Accurate estimate ±2%\nvs character-based ±40%
    CALC-->>Caller: 0.000855 (float)

    Note over Caller: Used for budget pre-check\n→ fewer false CostLimitExceptions\n→ more accurate budget protection
```

---

## Session Budget Lifecycle

A full session showing how multiple requests accumulate spending and eventually trigger the budget limit:

```mermaid
sequenceDiagram
    actor User as User Code
    participant COST as CostTrackingMiddleware
    participant CALC as CostCalculator
    participant API as Provider API

    Note over CALC: Session start: totalSpent = $0.00, budgetLimit = $5.00

    User->>COST: Request 1 (short prompt)
    COST->>CALC: estimate → $0.012
    Note over COST: $0.00 + $0.012 = $0.012 < $5.00 ✓
    COST->>API: send
    API-->>COST: response (actual cost: $0.015)
    COST->>CALC: addSpent($0.015)
    Note over CALC: totalSpent = $0.015

    User->>COST: Request 2 (medium prompt)
    COST->>CALC: estimate → $0.24
    Note over COST: $0.015 + $0.24 = $0.255 < $5.00 ✓
    COST->>API: send
    API-->>COST: response (actual cost: $0.27)
    COST->>CALC: addSpent($0.27)
    Note over CALC: totalSpent = $0.285

    User->>COST: Request 3 ... (more requests over time)
    Note over CALC: totalSpent grows → $4.85

    User->>COST: Request N (large prompt — tool loop)
    COST->>CALC: estimate → $0.38
    Note over COST: $4.85 + $0.38 = $5.23 > $5.00 ✗

    COST-->>User: throw CostLimitException\nbudgetLimit=$5.00\ntotalSpent=$4.85\nestimatedCost=$0.38

    Note over User: Catch exception → notify user\nor reset session budget
    User->>CALC: addSpent(-4.85) → reset
    Note over CALC: totalSpent = $0.00 (new session)
```

---

> **← Back:** [Request Lifecycle](request-lifecycle.md) · **Next:** [Architecture →](architecture.md)
