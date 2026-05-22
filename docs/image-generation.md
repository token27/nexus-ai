# Image Generation

NexusAI supports AI image generation via DALL-E 3 and compatible providers through `ImageCapableInterface`.

## Basic Usage

```php
use Token27\NexusAI\Request\ImageRequest;
use Token27\NexusAI\Contract\ImageCapableInterface;
use Token27\NexusAI\Driver\DriverRegistry;

$request = new ImageRequest(
    provider: 'openai',
    model: 'dall-e-3',
    prompt: 'A serene Japanese garden at sunset, photorealistic, 8K',
);

$driver = DriverRegistry::getInstance()->resolve('openai');

/** @var ImageCapableInterface $driver */
$response = $driver->image($request);

// Access the generated image
$image = $response->images[0];
echo $image->url;       // https://oaidalleapiprodscus.blob.core.windows.net/...
echo $image->revisedPrompt; // The prompt actually used by DALL-E
```

## Full ImageRequest Parameters

```php
$request = new ImageRequest(
    provider: 'openai',
    model: 'dall-e-3',
    prompt: 'A futuristic cityscape at night, neon lights, cyberpunk style',
    size: '1792x1024',       // '1024x1024' | '1792x1024' | '1024x1792'
    quality: 'hd',           // 'standard' | 'hd'
    style: 'vivid',          // 'natural' | 'vivid'
    n: 1,                    // Number of images (DALL-E 3 only supports 1)
    responseFormat: 'url',   // 'url' | 'b64_json'
);
```

## Size Options

| Size | Aspect Ratio | Best For |
|------|-------------|----------|
| `1024x1024` | Square | Icons, avatars, social media |
| `1792x1024` | Landscape (16:9) | Headers, banners, scenes |
| `1024x1792` | Portrait (9:16) | Mobile, posters, characters |

## Response Object

```php
$response->images;       // array<GeneratedImage>
$response->finishReason; // FinishReason::Stop (always)
$response->meta;         // Meta (model, id)
```

### GeneratedImage Value Object

```php
$image = $response->images[0];

$image->url;           // string|null — direct URL (if responseFormat = 'url')
$image->b64Json;       // string|null — base64 data (if responseFormat = 'b64_json')
$image->revisedPrompt; // string|null — DALL-E's rewritten prompt
```

## Save to File

```php
$request = new ImageRequest(
    provider: 'openai',
    model: 'dall-e-3',
    prompt: 'A PHP elephant mascot, cartoon style',
    responseFormat: 'b64_json',
);

$response = $driver->image($request);
$image = $response->images[0];

file_put_contents('elephant.png', base64_decode($image->b64Json));
```

## Download from URL

```php
$request = new ImageRequest(
    provider: 'openai',
    model: 'dall-e-3',
    prompt: 'Abstract watercolor landscape',
    responseFormat: 'url',
);

$response = $driver->image($request);
$url = $response->images[0]->url;

// Download and save
$imageData = file_get_contents($url);
file_put_contents('landscape.png', $imageData);
```

## Provider Support

| Provider | Image Generation | Notes |
|----------|-----------------|-------|
| OpenAI | ✅ DALL-E 3 | Full support |
| Anthropic | ❌ | Not supported |
| Gemini | ✅ Imagen | Via Gemini driver |
| Ollama | ❌ | Not supported |

> **Note:** Check `$driver->supports('image')` to verify capability before calling `$driver->image()`.

---

> **Related:** [Text Generation](text-generation.md) · [Streaming](streaming.md)
