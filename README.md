# NexusAI

[![CI](https://github.com/token27/nexus-ai/actions/workflows/ci.yml/badge.svg)](https://github.com/token27/nexus-ai/actions)
[![Latest Version](https://img.shields.io/packagist/v/token27/nexus-ai.svg?style=flat-square)](https://packagist.org/packages/token27/nexus-ai)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![PHPStan Level 6](https://img.shields.io/badge/PHPStan-Level%206-1f6feb)](https://phpstan.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A framework-agnostic PHP 8.2+ library for communicating with AI providers. Supports text generation, image generation, embeddings, speech synthesis, transcription, streaming, tool calling, and structured output — through a unified, PSR-18 compliant interface.

## Supported Providers

OpenAI · Anthropic · Google Gemini · Ollama · DeepSeek · Groq · Mistral · xAI · Perplexity

## Features

- **Text generation** with full conversation history support
- **Image generation** (DALL-E 3, Imagen)
- **Text embeddings** with batch support
- **Text-to-Speech** and **Speech-to-Text** (Whisper)
- **Streaming** via Server-Sent Events with typed chunks
- **Structured output** — maps LLM responses directly to PHP DTOs
- **Tool calling** with multi-step execution loops
- **Chat history** — in-memory and file-based persistence
- **Middleware pipeline** — Retry, Cache, Cost tracking, Rate limiting, Circuit breaker, Logging
- **Observability** — EventBus with pluggable observers and metrics
- **FakeDriver** for unit testing without API calls

## Installation

```bash
composer require token27/nexus-ai
```

Requires a PSR-18 HTTP client:

```bash
composer require guzzlehttp/guzzle
```

## Quick Start

```php
use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();

NexusAI::setHttpClient(new Client());
NexusAI::setFactories($factory, $factory);
NexusAI::configure([
    'openai' => ['api_key' => $_ENV['OPENAI_API_KEY']],
]);

$response = NexusAI::using('openai', 'gpt-4o')
    ->withSystemPrompt('You are a helpful assistant.')
    ->withPrompt('What is PHP?')
    ->asText();

echo $response->text;
```

## Documentation

Full documentation is available in the [`docs/`](docs/) directory:

- [Installation & Setup](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Text Generation](docs/text-generation.md)
- [Image Generation](docs/image-generation.md)
- [Embeddings](docs/embeddings.md)
- [Audio — TTS & STT](docs/audio.md)
- [Structured Output](docs/structured-output.md)
- [Streaming](docs/streaming.md)
- [Tool Calling](docs/tool-calling.md)
- [Chat History](docs/chat-history.md)
- [Middleware Pipeline](docs/middleware.md)
- [Observability](docs/observability.md)
- [Testing](docs/testing.md)
- [Troubleshooting](docs/troubleshooting.md)

## Requirements

- PHP 8.2 or higher
- A PSR-18 HTTP client
- PSR-17 HTTP factories

## Contributing

Please see [docs/contributing.md](docs/contributing.md) for details.

## License

MIT. Please see [LICENSE](LICENSE) for more information.
