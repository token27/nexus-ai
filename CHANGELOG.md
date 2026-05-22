# Changelog

All notable changes to NexusAI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-05-20

### Added

- **`token27/nexus-ai-pricing` integration**: Cost calculation and budget management are now powered by the dedicated `token27/nexus-ai-pricing` library, enabling a full pricing engine with configurable price tables, cache-aware billing, and a rich `PricingResultInterface`.
- **`PricingEngine`** (`token27/nexus-ai-pricing`): Factory-based engine built with `PricingEngine::withTable(PriceTableInterface $table)`. Supports `ArrayPriceTable`, `DefaultPriceCatalog`, custom price tables, and per-engine runtime price registration.
- **`DefaultPriceCatalog`**: Built-in pricing for all major models (OpenAI, Anthropic, Gemini, DeepSeek, Mistral, and more). Use `DefaultPriceCatalog::get()` to retrieve the full list.
- **`ModelPrice`**: Value object for per-model pricing with full cache support: `inputPerMillion`, `outputPerMillion`, `cacheWritePerMillion`, `cacheReadPerMillion`, `cacheReadIsSubsetOfInput` (for OpenAI-style subset cache billing).
- **Anthropic-style cache billing**: `cacheWrite` and `cacheRead` tokens are billed additively alongside input tokens.
- **OpenAI-style cache billing**: `cacheRead` tokens are a subset of `inputTokens`; pricing engine avoids double-counting by adjusting accordingly.
- **Per-request pricing engine override**: `PendingRequest::withPricingEngine(PricingEngineInterface $engine)` allows specifying custom prices for a single request without changing the global calculator.
- **`PricingResultInterface`**: 18-method interface returned after every costed request. Exposes `totalCostUsd()`, `inputCostUsd()`, `outputCostUsd()`, `cacheWriteCostUsd()`, `cacheReadCostUsd()`, `cacheSavingsUsd()`, `isUnknownModel()`, `inputTokens()`, `outputTokens()`, `cacheWriteTokens()`, `cacheReadTokens()`, `model()`, `currency()`, `isZero()`, `format()`, `formatDetailed()`, `toArray()`, `add()`.
- **`PricingResult::compute()`** and **`PricingResult::unknown()`**: Factory methods for creating concrete pricing results (useful in tests and offline calculations).
- **`docs/pricing.md`**: New comprehensive documentation page covering engine setup, budget limits, cache billing (Anthropic and OpenAI models), per-request overrides, `PricingResultInterface` reference, and aggregation patterns.
- **PHPStan Level 8**: Static analysis upgraded from Level 6 to Level 8 with 0 errors across all source files.

### Changed

- **`CostCalculator` constructor**: Now requires a `PricingEngineInterface` argument. Before: `new CostCalculator()`. After: `new CostCalculator($engine)`. **Breaking change.**
- **`Context::getPricingResult()`**: Replaces the removed `getCost()` method and returns `?PricingResultInterface` instead of `?Cost`. **Breaking change.**
- **`Context::withPricingResult()`**: Replaces the removed `withCost()` method. **Breaking change.**
- **`RequestCompleted::$pricingResult`**: Event property renamed from `$cost` (type `Cost`) to `$pricingResult` (type `?PricingResultInterface`). **Breaking change** for custom observers.
- **`CostLimitException`**: Now carries `estimatedCost` as a `float` from the pricing engine estimate instead of a rough character-count approximation.

### Removed

- **`Token27\NexusAI\ValueObject\Cost`**: Replaced by `PricingResultInterface` from `token27/nexus-ai-pricing`. Any code that directly instantiated `new Cost(...)` or called `$cost->totalCost()` / `$cost->format()` must be updated. **Breaking change.**

---

## [1.0.0] - 2026-05-13

### Added

#### Core Architecture
- **NexusAI Facade**: Static entry point with `NexusAI::using()`, `configure()`, and `registerDriver()`
- **PendingRequest**: Fluent builder for configuring AI requests (`withPrompt()`, `withTools()`, `asText()`, `asStream()`, `asStructured()`)
- **Pipeline**: Immutable onion-model middleware pipeline with `pipe()` and `send()`
- **Context**: Immutable pipeline context carrying request, response, usage, cost, and metadata
- **DriverRegistry**: Dynamic driver factory registry with PSR-18/17 dependency injection

#### Providers (9 total)
- **OpenAI** — Native driver with full protocol support
- **Anthropic** — Own driver (x-api-key auth, system field, tool_use/tool_result blocks)
- **Gemini** — Own driver (query param auth, user/model roles, parts-based content)
- **Ollama** — Own driver (OpenAI-compatible, no auth, local server support)
- **DeepSeek** — Own driver (OpenAI-compatible + reasoning_content extraction)
- **Groq** — OpenAI-compatible via OpenAIDriver with Groq base URL
- **Mistral** — OpenAI-compatible via OpenAIDriver with Mistral base URL
- **XAI** — OpenAI-compatible via OpenAIDriver with XAI base URL
- **Perplexity** — OpenAI-compatible via OpenAIDriver with Perplexity base URL

#### Middleware Pipeline (7 middlewares)
- **RetryMiddleware** — Exponential backoff with jitter for retryable exceptions
- **CostTrackingMiddleware** — Per-model cost calculation with budget limits (innovation: no benchmark library has this)
- **CacheMiddleware** — PSR-16 response caching with deterministic keys and short-circuit
- **RateLimitMiddleware** — Pre-flight rate limit checking from response headers
- **CircuitBreakerMiddleware** — Failure threshold with cooldown period
- **LoggingMiddleware** — PSR-3 request/response logging
- **ValidationMiddleware** — Pre-flight request validation

#### Structured Output
- **SchemaGenerator** — Reflection-based JSON Schema generation from PHP DTOs
- **SchemaProperty** — PHP attribute for schema metadata (description, enum, examples)
- **Deserializer** — Object hydration via `newInstanceWithoutConstructor()` with type casting
- **JsonExtractor** — Resilient JSON extraction from LLM text (markdown blocks, braces, brackets)
- **Validator** — Attribute-based validation with `ValidationRuleInterface`

#### Tool System
- **Tool** — Fluent builder with `make()`, `addProperty()`, `setCallable()`
- **ToolProperty** — Typed parameter definitions with PropertyType enum
- **ToolRegistry** — Tool storage with `toSchemaArray()` for API payloads
- **ToolExecutor** — Sequential and parallel (pcntl) tool execution with error isolation

#### Streaming
- **StreamResponse** — Generator-based streaming with `text()` iterator and `collect()` materializer
- **TextChunk / ToolCallChunk / UsageChunk** — Typed StreamChunkInterface implementations
- **OpenAIStreamParser** — SSE parser for OpenAI/compatible providers
- **AnthropicStreamParser** — SSE parser for Anthropic's typed event protocol

#### Observability
- **EventBus** — Synchronous event emission with observer error isolation
- **MetricsObserver** — Aggregates tokens, cost, latency, error rate in real-time
- **RequestCompleted / RequestFailed** — Typed event objects

#### Value Objects (immutable)
- **Usage** — Token consumption (prompt + completion + cache tokens)
- **Cost** — Financial cost with `totalCost()` and `format()`
- **ToolCall / ToolResult** — Tool interaction data
- **ContentBlock** — Multi-modal content (text, image URL, image base64, audio, file)
- **Meta** — Response metadata (id, model, rate limits)
- **RateLimitInfo** — Rate limit data from response headers

#### Exceptions
- **NexusException** — Base exception for all library errors
- **DriverException** — Provider communication errors with status code
- **CostLimitException** — Budget exceeded with spending details
- **RateLimitException** — Rate limit reached with limit info
- **StructuredOutputException** — Schema/deserialization/validation failures
- **ToolException** — Tool execution errors
- **StreamException** — SSE parsing errors

#### Testing
- **FakeDriver** — Queue responses, record requests, assert prompts/models/tools
- 124 unit tests with 240 assertions covering all core components

#### Quality
- PHPStan Level 6 — 0 errors
- PHP CS Fixer — PSR-12 compliant
- PHPUnit 11.x with strict mode

### Security
- Budget limits prevent runaway costs from tool-calling loops
- Circuit breaker prevents cascading failures
- Rate limit pre-checking avoids wasted 429 responses
