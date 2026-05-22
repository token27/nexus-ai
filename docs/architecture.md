# Architecture & Component Map

Visual reference for NexusAI's static structure: library dependencies, internal components, and provider wiring. For runtime flows, see [Request Lifecycle →](request-lifecycle.md).

---

## Library Ecosystem

Three focused libraries form the NexusAI ecosystem. `token27/nexus-ai-pricing` is a **required** dependency. `token27/nexus-ai-tokenizer` is **optional** — it improves pre-request cost estimation from ±40% (character-based) to ±2% (BPE token-based).

```mermaid
graph TB
    APP["Your PHP Application\n(Laravel / Symfony / CakePHP / Standalone)"]

    subgraph CORE ["Core"]
        NEXUS["token27/nexus-ai\nAI communication engine\nMiddleware pipeline · 9 drivers\nStreaming · Tools · Structured output"]
        PRICING["token27/nexus-ai-pricing\nCost calculation engine\nDefaultPriceCatalog · Budget limits\nAnthropic + OpenAI cache billing"]
    end

    subgraph OPT ["Optional Enhancement"]
        TOKENIZER["token27/nexus-ai-tokenizer\nBPE tokenization engine\nAccurate pre-request cost estimation\n±40% → ±2% accuracy"]
    end

    subgraph PROVIDERS ["External AI Providers"]
        OAI["OpenAI"]
        ANT["Anthropic"]
        GEM["Google Gemini"]
        OLL["Ollama (local)"]
        DSK["DeepSeek"]
        COMPAT["Groq · Mistral · xAI · Perplexity"]
    end

    APP -->|"NexusAI::using()"| NEXUS
    NEXUS -->|"composer require"| PRICING
    NEXUS -.->|"optional"| TOKENIZER
    PRICING -.->|"uses for estimation when installed"| TOKENIZER
    NEXUS -->|"PSR-18 HTTP (no framework coupling)"| PROVIDERS
```

---

## Feature Mind Map

A bird's-eye view of everything NexusAI provides:

```mermaid
mindmap
  root((NexusAI))
    Request Types
      Text Generation
      Structured Output
      Embeddings
      Streaming SSE
    Providers
      OpenAI
      Anthropic
      Google Gemini
      Ollama local
      DeepSeek
      Groq / Mistral
      xAI / Perplexity
    Middleware Pipeline
      RetryMiddleware
        Exponential backoff
        Jitter
      CostTrackingMiddleware
        Budget limits
        Per-request overrides
      CacheMiddleware
        PSR-16
        Cache hit short-circuit
      CircuitBreakerMiddleware
        Failure threshold
        Cooldown period
      RateLimitMiddleware
        Pre-flight check
        Header parsing
      LoggingMiddleware
        PSR-3 compatible
    Pricing
      DefaultPriceCatalog
      Custom price tables
      Anthropic cache billing
      OpenAI cache billing
      PricingResultInterface
    Observability
      EventBus
      MetricsObserver
        Latency P50/P95/P99
        Per-provider breakdown
      Custom observers
    Tool System
      Tool builder
      ToolRegistry
      ToolExecutor
      Multi-step loops
    Chat History
      InMemoryChatHistory
      FileChatHistory
      Token trimming
    Structured Output
      SchemaGenerator
      JSON extraction
      Deserializer
      Validation attributes
      Auto-retry with feedback
    Testing
      FakeDriver
      Response queuing
      Request assertions
```

---

## Internal Component Architecture

```mermaid
graph TB
    subgraph ENTRY ["Entry Layer"]
        FACADE["NexusAI Facade\nStatic entry point · Singleton registry\nGlobal pipeline builder"]
        PENDING["PendingRequest\nFluent builder pattern\nwithPrompt · withTools · withPricingEngine\nasText · asStream · asStructured · asEmbeddings"]
    end

    subgraph PIPELINE ["Pipeline Layer"]
        PL["Pipeline\nImmutable onion model\narray_reduce composition\npipe() returns NEW instance"]
        CTX["Context\nImmutable pipeline carrier\nRequest · Response · Usage\nPricingResult · Metadata\nwith() methods return clones"]
    end

    subgraph STACK ["Global Middleware Stack"]
        direction LR
        RET["RetryMiddleware\nExponential backoff\nJitter · max retries"]
        COST["CostTrackingMiddleware\nPre: estimate + budget check\nPost: actual cost + attach result"]
        CACHE["CacheMiddleware\nPSR-16 · TTL · short-circuit"]
        CIRC["CircuitBreakerMiddleware\nFailure threshold · Cooldown"]
        RATE["RateLimitMiddleware\nPre-flight header check"]
        LOGMW["LoggingMiddleware\nPSR-3 · before/after/error"]
        VAL["ValidationMiddleware\nPre-flight request check"]
    end

    subgraph DRIVER_LAYER ["Driver Layer"]
        REG["DriverRegistry\nFactory map (string → callable)\nPSR-18/17 dependency injection"]
        ABS["AbstractDriver\nHTTP transport (sendRequest)\nTool loop (executeWithToolLoop)\nStructured output (executeStructured)\nError → typed exceptions"]
        D1["OpenAIDriver\n+ Groq · Mistral · xAI · Perplexity"]
        D2["AnthropicDriver"]
        D3["GeminiDriver"]
        D4["OllamaDriver · DeepSeekDriver"]
    end

    subgraph PRICING_LAYER ["Pricing Layer"]
        CALC["CostCalculator\nWraps PricingEngine\nTracks session total spending\ncalculate() · estimate() · registerPrice()"]
        PENG["PricingEngine\nPrice lookup · Cost math\nCache-aware billing\nAnthropicsubset vs additive"]
        PTABLE["PriceTable\nArrayPriceTable\nDefaultPriceCatalog"]
    end

    subgraph OBS ["Observability Layer"]
        EBUS["EventBus\nSubscribe · Unsubscribe · Emit\nObserver errors silently caught\nNever interrupts main flow"]
        MOBS["MetricsObserver\nRequests · Tokens · Cost\nLatency P50/P95/P99\nError rate · Per-provider"]
        LOBS["LogObserver\nPSR-3 structured logging"]
    end

    FACADE --> PENDING
    PENDING --> PL
    PL --> CTX
    PL --> STACK
    STACK --> REG
    REG --> ABS
    ABS --> D1 & D2 & D3 & D4
    COST --> CALC
    CALC --> PENG
    PENG --> PTABLE
    EBUS --> MOBS & LOBS
    PL -.->|"user wires via ObservabilityMiddleware"| EBUS
```

---

## Provider Driver Matrix

Nine providers are supported. Five have their own driver with distinct protocol implementations. Four are OpenAI-compatible and reuse `OpenAIDriver` with a different `baseUrl`.

```mermaid
graph LR
    subgraph OWN ["Own Protocol Drivers"]
        OD["OpenAIDriver\n———\nAuth: Bearer token\nEndpoint: /chat/completions\nTools: function_call / tools\nStream: SSE delta events\nStructured: response_format"]
        AD["AnthropicDriver\n———\nAuth: x-api-key header\nEndpoint: /messages\nSystem: separate top-level field\nTools: tool_use / tool_result blocks\nStream: typed event protocol"]
        GD["GeminiDriver\n———\nAuth: ?key= query param\nEndpoint: /generateContent\nRoles: user / model\nContent: parts-based array"]
        OLLAD["OllamaDriver\n———\nAuth: none required\nBase URL: localhost:11434\nOpenAI-compatible API format"]
        DSD["DeepSeekDriver\n———\nOpenAI-compatible +\nreasoning_content extraction\nfor chain-of-thought models"]
    end

    subgraph COMPAT ["Reuse OpenAIDriver with different base URL"]
        GRQ["Groq\napi.groq.com"]
        MST["Mistral\napi.mistral.ai"]
        XAI["xAI\napi.x.ai"]
        PPX["Perplexity\napi.perplexity.ai"]
    end

    OD -.->|"shared driver code"| GRQ & MST & XAI & PPX
```

---

## Context: The Immutable Pipeline Carrier

`Context` is the single object that flows through the entire middleware stack. Each `with*()` method returns a **new clone** — no middleware can mutate another's state.

```mermaid
classDiagram
    direction TB

    class Context {
        -RequestInterface request
        -ResponseInterface? response
        -Usage? usage
        -PricingResultInterface? pricingResult
        -array metadata
        -float startTime
        +getRequest() RequestInterface
        +getResponse() ResponseInterface?
        +getUsage() Usage?
        +getPricingResult() PricingResultInterface?
        +getElapsedMs() float
        +getMeta(key, default) mixed
        +getAllMeta() array
        +withResponse(response) Context
        +withUsage(usage) Context
        +withPricingResult(result) Context
        +withMeta(key, value) Context
        +create(request)$ Context
    }

    class RequestInterface {
        <<interface>>
        +getProvider() string
        +getModel() string
        +getMessages() array
        +getOptions() array
    }

    class TextRequest {
        +string provider
        +string model
        +array messages
        +string? systemPrompt
        +int? maxTokens
        +float? temperature
        +array tools
        +int maxSteps
        +resolveMessages() array
    }

    class StructuredRequest {
        +string outputClass
        +StructuredMode mode
        +int maxRetries
    }

    class EmbeddingRequest {
        +string input
    }

    class Usage {
        +int promptTokens
        +int completionTokens
        +int cacheWriteTokens
        +int cacheReadTokens
        +totalTokens() int
    }

    class PricingResultInterface {
        <<interface>>
        +totalCostUsd() float
        +inputCostUsd() float
        +outputCostUsd() float
        +cacheWriteCostUsd() float
        +cacheReadCostUsd() float
        +cacheSavingsUsd() float
        +inputTokens() int
        +outputTokens() int
        +isUnknownModel() bool
        +isZero() bool
        +format() string
        +formatDetailed() string
        +toArray() array
        +add(other) PricingResultInterface
    }

    class TextResponse {
        +string text
        +FinishReason finishReason
        +Usage usage
        +array toolCalls
        +Meta? meta
        +array steps
    }

    RequestInterface <|.. TextRequest
    RequestInterface <|.. StructuredRequest
    RequestInterface <|.. EmbeddingRequest
    Context --> RequestInterface : carries
    Context --> TextResponse : carries after driver
    Context --> Usage : carries after driver
    Context --> PricingResultInterface : carries after CostTracking
    TextResponse --> Usage
```

---

> **← Back:** [Documentation Index](README.md) · **Next:** [Request Lifecycle →](request-lifecycle.md)
