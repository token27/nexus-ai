# Request Lifecycle

Visual reference for how requests flow through NexusAI at runtime: from user code to the provider API and back through the middleware stack. For static structure, see [Architecture →](architecture.md). For pricing flows, see [Pricing Flow →](pricing-flow.md).

---

## Full Text Request Lifecycle

The complete journey of a `asText()` call from user code through the middleware onion, driver, HTTP, and back:

```mermaid
sequenceDiagram
    actor User as User Code
    participant PR as PendingRequest
    participant PL as Pipeline
    participant RET as RetryMiddleware
    participant COST as CostTrackingMiddleware
    participant CACHE as CacheMiddleware
    participant DR as Driver (e.g. OpenAIDriver)
    participant API as Provider API (HTTPS)

    User->>PR: NexusAI::using('openai','gpt-4o')
    Note over PR: Fluent builder: accumulates options
    User->>PR: ->withPrompt('Hello')
    User->>PR: ->withMaxTokens(500)
    User->>PR: ->asText()

    PR->>PR: buildTextRequest()
    PR->>PL: pipeline->send(context, driver_handler)
    Note over PL: Composes onion via array_reduce

    PL->>RET: Retry->process(ctx, next)
    Note over RET: Wraps everything for auto-retry on RateLimitException

    RET->>COST: Cost->process(ctx, next)
    Note over COST: BEFORE: estimate cost + budget check

    COST->>CACHE: Cache->process(ctx, next)
    Note over CACHE: Check cache key — MISS → continue

    CACHE->>DR: handler(ctx) → driver.text(request)
    DR->>DR: buildPayload() → JSON
    DR->>API: POST /chat/completions
    Note over API: OpenAI / Anthropic / Gemini etc.
    API-->>DR: 200 OK {choices, usage}
    DR->>DR: parseResponse() → TextResponse
    DR-->>CACHE: Context{response, usage}

    Note over CACHE: Store response in PSR-16 cache
    CACHE-->>COST: Context{response, usage}

    Note over COST: AFTER: calculate actual cost → attach PricingResult
    COST-->>RET: Context{response, usage, pricingResult}

    Note over RET: No error → pass through
    RET-->>PL: Context (final)

    PL-->>PR: Context (final)
    PR-->>User: TextResponse{text, finishReason, usage}
```

---

## Middleware Onion Execution Model

The pipeline composes middlewares using `array_reduce(array_reverse($middlewares), ...)`. The result is a nested callable — middleware `A` wraps `B` which wraps `C` which wraps the driver handler `H`:

```mermaid
flowchart LR
    subgraph ONION ["Execution Order: Request flows IN → OUT, Response flows OUT → IN"]
        direction LR
        subgraph A ["A: RetryMiddleware"]
            subgraph B ["B: CostTrackingMiddleware"]
                subgraph C ["C: CacheMiddleware"]
                    subgraph D ["D: LoggingMiddleware"]
                        H["H: Driver Handler\n(actual API call)"]
                    end
                end
            end
        end
    end

    REQ(["Request\n→"]) --> A
    A --> B
    B --> C
    C --> D
    D --> H
    H --> D
    D --> C
    C --> B
    B --> A
    A --> RES(["← Response"])
```

Each middleware calls `$next($context)` to pass control inward. On the way back out, each middleware can inspect or modify the context (e.g., `CostTrackingMiddleware` attaches `PricingResult` on the return trip).

---

## Error Propagation Through Middleware

What happens when an exception is thrown at any layer:

```mermaid
flowchart TD
    REQ["Incoming Request"] --> A
    A["RetryMiddleware"] -->|"calls next()"| B
    B["CostTrackingMiddleware"] -->|"calls next()"| C
    C["CacheMiddleware"] -->|"calls next()"| H
    H["Driver Handler"] -->|"HTTP 429"| E1{{"RateLimitException"}}
    E1 -->|"bubbles up"| C
    C -->|"re-throws"| B
    B -->|"re-throws"| A
    A -->|"catches RateLimitException"| RETRY{{"Retry attempt < maxRetries?"}}
    RETRY -->|"Yes: sleep + retry"| B
    RETRY -->|"No: re-throw"| USER{{"Exception reaches user"}}

    H2["Driver Handler"] -->|"HTTP 401"| E2{{"DriverException (401)"}}
    E2 -->|"bubbles up through all layers"| USER2{{"Exception reaches user"}}

    style E1 fill:#ff9999
    style E2 fill:#ff9999
    style USER fill:#ffcccc
    style USER2 fill:#ffcccc
```

---

## Streaming Request Flow

Streaming differs from regular requests in one critical way: the pipeline runs **before** streaming begins, so middlewares (CostTracking, CircuitBreaker, etc.) can inspect and block the request. The generator itself runs **outside** the pipeline.

```mermaid
sequenceDiagram
    actor User as User Code
    participant PR as PendingRequest
    participant PL as Pipeline
    participant STACK as Middleware Stack
    participant DR as Driver
    participant API as Provider API

    User->>PR: ->asStream()
    PR->>PL: pipeline->send(context, mark_streaming_handler)
    Note over STACK: All middlewares run (budget check, circuit breaker, etc.)
    Note over STACK: Handler just marks ctx with _streaming=true
    PL-->>PR: Context (middlewares have run, no API call yet)

    PR->>DR: driver->stream(request)
    DR->>API: POST /chat/completions {stream: true}
    Note over API: Connection stays open (SSE)

    loop For each SSE event
        API-->>DR: data: {"delta": {"content": "..."}}
        DR->>DR: StreamParser::parse(line)
        DR-->>User: yield TextChunk{text, finishReason}
    end

    API-->>DR: data: [DONE]
    DR-->>User: yield UsageChunk{usage}
    Note over User: Generator exhausted — stream complete
```

**Key difference from regular requests:** Cost tracking does NOT automatically run for streams (no `Usage` returned mid-stream). If you need cost tracking for streams, collect the stream with `$stream->collect()` then inspect `usage`.

---

## Tool Calling Loop

When tools are configured, the driver enters an automatic multi-step loop. Each iteration: LLM responds with tool calls → executor runs the tools → results are appended → next LLM call.

```mermaid
stateDiagram-v2
    [*] --> BuildRequest: asText() called with tools
    BuildRequest --> SendToLLM: build TextRequest for current step

    SendToLLM --> ParseResponse: HTTP response received

    ParseResponse --> CheckContinue: check finishReason + step count

    CheckContinue --> ExecuteTools: finishReason == ToolCalls\nAND executor != null\nAND step < maxSteps
    CheckContinue --> ReturnFinal: finishReason == Stop\nOR step >= maxSteps\nOR no tools configured

    ExecuteTools --> AppendResults: ToolExecutor runs each tool callable

    AppendResults --> BuildRequest: append ToolCallMessage + ToolResultMessage\nto messages array, step++

    ReturnFinal --> [*]: return TextResponse\n(accumulated usage across all steps\nsteps[] contains intermediate responses)

    note right of ExecuteTools
        Each tool callable runs in isolation.
        If a tool throws, ToolResult.isError=true.
        LLM sees the error and decides next action.
    end note

    note right of AppendResults
        Messages grow with each step:
        [user, assistant(toolCall), toolResult,
         assistant(toolCall), toolResult, ...]
    end note
```

### Tool Loop Sequence (Detailed)

```mermaid
sequenceDiagram
    actor User as User Code
    participant DR as AbstractDriver
    participant LLM as Provider API
    participant EX as ToolExecutor

    User->>DR: driver->text(request with tools)
    Note over DR: step=0, executor created from tool registry

    DR->>LLM: POST /chat/completions (step 0)
    LLM-->>DR: {finish_reason: "tool_calls", tool_calls: [{name:"get_weather", args:{city:"Madrid"}}]}

    Note over DR: shouldContinueToolLoop? YES → continue

    DR->>EX: executor->execute(toolCalls)
    EX->>EX: run get_weather({city:"Madrid"})
    EX-->>DR: [ToolResult{result: '{"temp":22}', isError:false}]

    DR->>DR: append ToolCallMessage + ToolResultMessage to messages

    DR->>LLM: POST /chat/completions (step 1, messages + tool results)
    LLM-->>DR: {finish_reason: "stop", content: "Madrid is 22°C"}

    Note over DR: shouldContinueToolLoop? NO → finishReason=Stop

    DR-->>User: TextResponse{text:"Madrid is 22°C", usage: combined step0+step1}
```

---

## Structured Output Pipeline

`asStructured(MyDto::class)` adds a retry loop with LLM feedback. The driver uses reflection to generate a JSON Schema and sends it as a system prompt instruction.

```mermaid
flowchart TD
    START["asStructured(MyDto::class) called"] --> SCHEMA
    SCHEMA["SchemaGenerator::generate(MyDto)"] --> BUILD
    BUILD["Build TextRequest\nSchema injected into system prompt\n'Respond ONLY with JSON matching this schema'"]

    BUILD --> SEND["driver->text(TextRequest)"]
    SEND --> EXTRACT["JsonExtractor::extract(response.text)"]

    EXTRACT --> JSONOK{Valid JSON\nextracted?}
    JSONOK -- No --> JARETRY{Attempts\nremaining?}
    JARETRY -- Yes --> JAFEED["Append:\nassistant(bad response)\nuser('Your response was not valid JSON...')"]
    JAFEED --> SEND
    JARETRY -- No --> ERR1{{"StructuredOutputException\n'Failed to extract JSON'"}}

    JSONOK -- Yes --> DESER["Deserializer::deserialize(json, MyDto::class)"]
    DESER --> DESEROK{Deserialization\nsuccessful?}
    DESEROK -- No --> DSRETRY{Attempts\nremaining?}
    DSRETRY -- Yes --> DSFEED["Append:\nassistant(bad response)\nuser('JSON deserialization failed: ...')"]
    DSFEED --> SEND
    DSRETRY -- No --> ERR2{{"StructuredOutputException\n'Deserialization failed'"}}

    DESEROK -- Yes --> VALID["Validator::validate(object)"]
    VALID --> VALIDOK{Validation\nviolations?}
    VALIDOK -- None --> SUCCESS["StructuredResponse{object, text, finishReason, attempts}"]
    VALIDOK -- Has violations --> VRETRY{Attempts\nremaining?}
    VRETRY -- Yes --> VFEED["Append:\nassistant(bad response)\nuser('Your JSON had validation errors:\n- field must be >= 10 chars\n...')"]
    VFEED --> SEND
    VRETRY -- No --> ERR3{{"StructuredOutputException\n'Validation failed after N attempts'"}}

    style ERR1 fill:#ff9999
    style ERR2 fill:#ff9999
    style ERR3 fill:#ff9999
    style SUCCESS fill:#99ff99
```

---

## Cache Hit vs Cache Miss

When `CacheMiddleware` is in the stack, a deterministic cache key is computed from the request. On a hit, the driver is never called.

```mermaid
flowchart LR
    REQ["Incoming Request"] --> KEY["Build cache key\nhash(provider + model + messages + options)"]
    KEY --> HIT{Cache hit?}

    HIT -- Yes --> RETURN["Return cached Context\n(no HTTP call, instant response)"]
    RETURN --> END_HIT["Pipeline continues with cached response"]

    HIT -- No --> NEXT["next(context) → Driver → API"]
    NEXT --> RESP["Response received"]
    RESP --> STORE["Store in PSR-16 cache\nwith configured TTL"]
    STORE --> END_MISS["Pipeline continues with fresh response"]

    Note1["Note: CacheMiddleware\nskips tool requests\nand streaming requests"]
```

---

## Observability Integration Pattern

`EventBus` is not wired into the pipeline by default. To emit `RequestCompleted`/`RequestFailed` events, add an observability middleware:

```mermaid
sequenceDiagram
    participant PL as Pipeline
    participant OBS_MW as ObservabilityMiddleware
    participant INNER as Inner Middlewares + Driver
    participant EBUS as EventBus
    participant M1 as MetricsObserver
    participant M2 as SlackAlertObserver

    PL->>OBS_MW: process(ctx, next)
    OBS_MW->>EBUS: emit('request.started', this, RequestStarted{...})
    EBUS->>M1: onEvent('request.started', ...)
    EBUS->>M2: onEvent('request.started', ...)

    OBS_MW->>INNER: next(ctx)

    alt Success
        INNER-->>OBS_MW: Context{response, usage, pricingResult}
        OBS_MW->>EBUS: emit('request.completed', this, RequestCompleted{request, response, usage, pricingResult, elapsedMs})
        EBUS->>M1: onEvent('request.completed', ...) → accumulate metrics
        EBUS->>M2: onEvent('request.completed', ...) → alert if cost > $0.10
        OBS_MW-->>PL: Context
    else Exception thrown
        INNER-->>OBS_MW: throws Throwable
        OBS_MW->>EBUS: emit('request.failed', this, RequestFailed{request, exception, elapsedMs})
        EBUS->>M1: onEvent('request.failed', ...) → increment error count
        EBUS->>M2: onEvent('request.failed', ...) → send Slack alert
        OBS_MW-->>PL: re-throws exception
    end

    Note over EBUS: Observer errors are SILENTLY caught.\nA broken observer never breaks the request.
```

---

> **← Back:** [Architecture](architecture.md) · **Next:** [Pricing Flow →](pricing-flow.md)
