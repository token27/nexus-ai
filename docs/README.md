# NexusAI Documentation

Welcome to the NexusAI documentation. Find what you need:

## Getting Started

| | |
|---|---|
| [Installation](installation.md) | Requirements, Guzzle & Symfony setup, framework integration |
| [Configuration](configuration.md) | API keys, provider options, custom drivers, env vars |

## Core Features

| | |
|---|---|
| [Text Generation](text-generation.md) | Prompts, messages, vision, temperature, options |
| [Image Generation](image-generation.md) | DALL-E 3, sizes, quality, URL vs base64 |
| [Embeddings](embeddings.md) | Vectors, batch embedding, RAG pattern, cosine similarity |
| [Audio — TTS](audio.md) | Text-to-Speech, voices, formats, stream to browser |
| [Audio — STT](audio.md#speech-to-text--transcription-stt) | Whisper transcription, timestamps, language detection |
| [Structured Output](structured-output.md) | DTOs, schema generation, validation attributes |
| [Streaming](streaming.md) | SSE, chunk types, collect, SSE over HTTP |
| [Tool Calling](tool-calling.md) | Define tools, multi-step loops, error handling |
| [Chat History](chat-history.md) | InMemory & File persistence, multi-turn conversations |
| [Middleware Pipeline](middleware.md) | Retry, Cache, Cost, RateLimit, CircuitBreaker, Logging |
| [Pricing & Cost Tracking](pricing.md) | PricingEngine setup, budget limits, cache billing, PricingResultInterface |
| [Observability](observability.md) | EventBus, MetricsObserver, custom observers |

## Quality & Operations

| | |
|---|---|
| [Testing](testing.md) | FakeDriver, assertions, PHPUnit examples |
| [Troubleshooting](troubleshooting.md) | Common errors and fixes |
| [Contributing](contributing.md) | Dev setup, adding drivers/middlewares, PR guidelines |

## Architecture & Internals (Mermaid Diagrams)

Visual reference documents for understanding how NexusAI works internally. No code required — everything explained with diagrams.

| | |
|---|---|
| [Architecture & Component Map](architecture.md) | Ecosystem overview, component diagram, driver matrix, Context class diagram, feature mindmap |
| [Request Lifecycle](request-lifecycle.md) | Full request sequence, middleware onion model, tool calling loop, streaming flow, structured output pipeline, observability pattern |
| [Pricing & Cost Flow](pricing-flow.md) | CostTrackingMiddleware lifecycle, pre-request estimation decision tree, budget enforcement, Anthropic vs OpenAI cache billing, tokenizer integration |

---

> Back to [README](../README.md) · [Changelog](../CHANGELOG.md)
