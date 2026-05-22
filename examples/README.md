# Examples

Current structure:

- `examples/text` -> text generation by provider (+ detailed basic walkthrough)
- `examples/image` -> image generation by provider (+ detailed walkthrough)
- `examples/others` -> advanced scenarios (streaming, tools, middleware, pricing, observability, embeddings, integrations)

## Environment

Examples load variables from:

1. `nexus-ai/.env`
2. `nexus-ai/examples/.env` (fallback)

Use `.env.example` at project root as template.

## Quick start

```bash
php examples/text/01-openai.php
php examples/image/01-openai.php
```

Run all text examples:

```powershell
powershell -ExecutionPolicy Bypass -File examples/text/run-all.ps1
```

Run all image examples:

```powershell
powershell -ExecutionPolicy Bypass -File examples/image/run-all.ps1
```
