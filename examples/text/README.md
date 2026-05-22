# Text Examples by Provider

This folder contains one executable text-generation example per provider, plus one custom injected provider example.

## Files

- `00-detailed-basic-text-generation.php`
- `01-openai.php`
- `02-anthropic.php`
- `03-gemini.php`
- `04-deepseek.php`
- `05-ollama.php`
- `06-xai.php`
- `07-custom-injected-provider.php`

## Common output

Each example prints:

- text preview
- usage token breakdown
- pricing result (`total USD`, `format()`, `isUnknownModel()`)

## Run

From `nexus-ai/examples/text`:

```bash
php 00-detailed-basic-text-generation.php
php 01-openai.php
php 02-anthropic.php
php 03-gemini.php
php 04-deepseek.php
php 05-ollama.php
php 06-xai.php
php 07-custom-injected-provider.php
```

## Environment variables

- OpenAI: `OPENAI_API_KEY`, optional `OPENAI_TEXT_MODEL`
- Anthropic: `ANTHROPIC_API_KEY`, optional `ANTHROPIC_TEXT_MODEL`
- Gemini: `GEMINI_API_KEY`, optional `GEMINI_TEXT_MODEL`
- DeepSeek: `DEEPSEEK_API_KEY`, optional `DEEPSEEK_TEXT_MODEL`
- Ollama: optional `OLLAMA_BASE_URL`, `OLLAMA_TEXT_MODEL`
- xAI: `XAI_API_KEY`, optional `XAI_TEXT_MODEL`
- Custom injected provider:
  - optional `LOCAL_LLM_API_KEY`
  - optional `LOCAL_LLM_BASE_URL`
  - optional `LOCAL_LLM_MODEL`
