# Testing

NexusAI ships with `FakeDriver` for writing fast, deterministic unit tests — **no API keys, no network calls**.

## Basic Setup

```php
use Token27\NexusAI\Driver\Fake\FakeDriver;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\NexusAI;

$fake = new FakeDriver();

// Register as a provider
NexusAI::registerDriver('test', fn() => $fake);

// Or inject directly into DriverRegistry
```

## Queue Responses

```php
// Single response
$fake->willReturn(
    new TextResponse(text: 'Paris', finishReason: FinishReason::Stop)
);

// Multiple responses (consumed in order)
$fake->willReturn(
    new TextResponse(text: 'First', finishReason: FinishReason::Stop),
    new TextResponse(text: 'Second', finishReason: FinishReason::Stop),
);

// Per-model response
$fake->willReturnForModel('gpt-4o',
    new TextResponse(text: 'GPT-4o response', finishReason: FinishReason::Stop),
);
```

## Queue Exceptions

```php
use Token27\NexusAI\Exception\DriverException;

$fake->willReturn(
    new TextResponse(text: 'OK', finishReason: FinishReason::Stop)
); // First call succeeds

// Then queue an exception for next call
// (use a custom FakeDriver subclass or mock)
```

## Assertions

```php
// Number of requests received
$fake->assertRequestCount(2);

// Prompt content
$fake->assertPromptContains('capital of France');

// Model used
$fake->assertUsedModel('gpt-4o');

// Tool was called
$fake->assertToolWasCalled('get_weather');
```

## Inspect Recorded Requests

```php
$recorded = $fake->getRecorded(); // array<RequestInterface>
$last = $fake->getLastRecorded(); // RequestInterface|null

foreach ($recorded as $request) {
    echo $request->getModel();
    echo $request->getProvider();
    foreach ($request->getMessages() as $msg) {
        echo $msg->getText();
    }
}
```

## PHPUnit Example

```php
use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Driver\Fake\FakeDriver;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\Enum\FinishReason;

final class ArticleServiceTest extends TestCase
{
    public function testGeneratesArticleTitle(): void
    {
        $fake = new FakeDriver();
        $fake->willReturn(
            new TextResponse(text: 'PHP 8.4: What\'s New', finishReason: FinishReason::Stop)
        );

        $service = new ArticleService($fake);
        $title = $service->generateTitle('php 8.4 features');

        $this->assertSame('PHP 8.4: What\'s New', $title);
        $fake->assertPromptContains('php 8.4 features');
    }
}
```

## Reset Between Tests

```php
protected function setUp(): void
{
    NexusAI::reset();
}
```

---

> **← Back:** [Observability](observability.md) · **Next:** [Troubleshooting →](troubleshooting.md)
