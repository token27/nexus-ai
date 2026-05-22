# Installation

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | **8.3+** |
| PSR-18 HTTP Client | Any (Guzzle, Symfony HttpClient...) |
| PSR-17 Factories | Any |
| PSR-16 Cache | *Optional* — for CacheMiddleware |
| PSR-3 Logger | *Optional* — for LoggingMiddleware |

## Install via Composer

```bash
composer require token27/nexus-ai
```

This will automatically pull in the required `token27/nexus-ai-pricing` dependency, which provides the cost calculation and budget management engine.

## HTTP Client Setup

NexusAI is PSR-18 compliant — use any compatible HTTP client.

### With Guzzle

```bash
composer require guzzlehttp/guzzle
```

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Token27\NexusAI\NexusAI;

$guzzle = new Client();
$factory = new HttpFactory();

NexusAI::setHttpClient($guzzle);
NexusAI::setFactories($factory, $factory);
```

### With Symfony HttpClient

```bash
composer require symfony/http-client nyholm/psr7
```

```php
use Symfony\Component\HttpClient\Psr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$client = new Psr18Client();
$factory = new Psr17Factory();

NexusAI::setHttpClient($client);
NexusAI::setFactories($factory, $factory);
```

## Framework Integration

### Laravel

Create a service provider:

```php
// AppServiceProvider.php
NexusAI::configure([
    'openai' => ['api_key' => config('services.openai.key')],
]);
NexusAI::setHttpClient($laravelHttpClient);
```

### Symfony

Register as a service in `services.yaml` and inject the PSR-18 client from the container.

### CakePHP / standalone

Configure in bootstrap or a dedicated config file.

---

> **← Back:** [Documentation Index](README.md) · **Next:** [Configuration →](configuration.md)
