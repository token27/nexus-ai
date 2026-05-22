# Configuration

## Provider Configuration

Configure all providers at boot time with `NexusAI::configure()`:

```php
NexusAI::configure([
    'openai'      => ['api_key' => env('OPENAI_API_KEY')],
    'anthropic'   => ['api_key' => env('ANTHROPIC_API_KEY')],
    'gemini'      => ['api_key' => env('GEMINI_API_KEY')],
    'ollama'      => ['base_url' => 'http://localhost:11434/api'],
    'deepseek'    => ['api_key' => env('DEEPSEEK_API_KEY')],
    'groq'        => ['api_key' => env('GROQ_API_KEY')],
    'mistral'     => ['api_key' => env('MISTRAL_API_KEY')],
    'xai'         => ['api_key' => env('XAI_API_KEY')],
    'perplexity'  => ['api_key' => env('PERPLEXITY_API_KEY')],
]);
```

## Per-Provider Options

Each provider accepts:

| Key | Type | Description |
|-----|------|-------------|
| `api_key` | `string` | Authentication key |
| `base_url` | `string` | Override default endpoint |
| `options` | `array` | Default request options (e.g. `temperature`) |

```php
NexusAI::configure([
    'openai' => [
        'api_key'  => 'sk-...',
        'base_url' => 'https://api.openai.com/v1', // optional override
        'options'  => ['temperature' => 0.7],      // applied to all requests
    ],
]);
```

## Custom Drivers

Register your own driver factory:

```php
use Token27\NexusAI\Contract\DriverInterface;

NexusAI::registerDriver('my-provider', function (
    array $config,
    ClientInterface $http,
    RequestFactoryInterface $req,
    StreamFactoryInterface $stream,
): DriverInterface {
    return new MyCustomDriver($config, $http, $req, $stream);
});
```

## Environment Variables Reference

```ini
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=AIza...
GROQ_API_KEY=gsk_...
MISTRAL_API_KEY=...
XAI_API_KEY=...
PERPLEXITY_API_KEY=pplx-...
DEEPSEEK_API_KEY=...
OLLAMA_BASE_URL=http://localhost:11434/api
```

## Resetting State (useful in tests)

```php
NexusAI::reset(); // Clears all static state
```

---

> **← Back:** [Installation](installation.md) · **Next:** [Text Generation →](text-generation.md)
