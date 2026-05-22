# Image Examples by Provider

This folder contains image-generation examples for providers that are currently implemented in this library.

## Implemented in `nexus-ai`

- `00-detailed-image-generation.php`
- `01-openai.php`
- `02-xai.php`
- `03-gemini.php`
- `04-custom-injected-provider.php`

Each example prints:

- image count and first image details
- usage token breakdown
- pricing result (`total USD`, image output USD, and `format()`)

## Provider support matrix (as of 2026-05-22)

- `openai`: image generation supported, implemented here
- `xai`: image generation supported, implemented here
- `gemini`: image generation supported, implemented here
- `anthropic`: no prompt-to-image generation API (vision input only), not implemented for `asImage()`
- `deepseek`: no official image-generation endpoint in DeepSeek API docs, not implemented for `asImage()`
- `groq`: no image-generation endpoint in Groq API docs, not implemented for `asImage()`
- `perplexity`: no image-generation API in current public docs, not implemented for `asImage()`
- `mistral`: image generation exists via Agents tool API (different protocol), not implemented in current `OpenAIDriver` path
- `ollama`: OpenAI compatibility docs include image-generation fields, but this library currently keeps `OllamaDriver` focused on text endpoints

## Run

From `nexus-ai`:

```bash
php examples/image/00-detailed-image-generation.php
php examples/image/01-openai.php
php examples/image/02-xai.php
php examples/image/03-gemini.php
php examples/image/04-custom-injected-provider.php
```

Or run all:

```powershell
powershell -ExecutionPolicy Bypass -File examples/image/run-all.ps1
```

## Environment variables

- OpenAI: `OPENAI_API_KEY`, optional `OPENAI_IMAGE_MODEL`
- xAI: `XAI_API_KEY`, optional `XAI_IMAGE_MODEL`
- Gemini: `GEMINI_API_KEY`, optional `GEMINI_IMAGE_MODEL`
- Custom injected provider:
  - optional `LOCAL_IMAGE_API_KEY`
  - optional `LOCAL_IMAGE_BASE_URL`
  - optional `LOCAL_IMAGE_MODEL`
