# Troubleshooting

## Common Errors

### `LogicException: No responses queued in FakeDriver`

**Cause:** You called `$driver->text()` without queuing a response first.

**Fix:**
```php
$fake->willReturn(new TextResponse(text: 'Hello', finishReason: FinishReason::Stop));
```

---

### `CostLimitException: Budget limit exceeded`

**Cause:** `CostTrackingMiddleware` detected that accumulated spending + estimated cost exceeds the configured `budgetLimit`.

**Fix:** Either increase the budget, reset the calculator, or check for a runaway tool-calling loop.

```php
// Check current spending
echo $calculator->getTotalSpent(); // float (USD)

// Reset accumulated spending for a new session
$calculator->addSpent(-$calculator->getTotalSpent());
```

See [Pricing →](pricing.md) for a full explanation of `CostCalculator` setup and budget management.

---

### `DriverException` with status 401

**Cause:** Invalid or missing API key.

**Fix:** Check that `api_key` is correctly set in your config:
```php
NexusAI::configure(['openai' => ['api_key' => $_ENV['OPENAI_API_KEY']]]);
```

---

### `DriverException` with status 429

**Cause:** Rate limit hit. If using `RetryMiddleware`, it only retries `RateLimitException` subclasses automatically.

**Fix:** Add `RetryMiddleware` or handle the exception:
```php
use Token27\NexusAI\Exception\RateLimitException;

try {
    $response = NexusAI::using('openai', 'gpt-4o')->withPrompt('...')->asText();
} catch (RateLimitException $e) {
    sleep(60);
    // retry
}
```

---

### `StructuredOutputException: Failed to extract JSON`

**Cause:** The LLM returned text that couldn't be parsed as JSON matching the expected schema.

**Fix:** Use a stronger system prompt and specify the model supports JSON mode:
```php
NexusAI::using('openai', 'gpt-4o')
    ->withSystemPrompt('Always respond with valid JSON matching the requested schema. No explanations.')
    ->withOption('response_format', ['type' => 'json_object'])
    ->withPrompt('...')
    ->asStructured(MyDto::class);
```

---

### PHPStan memory limit error

**Cause:** PHPStan needs more than the default 128M PHP memory limit.

**Fix:**
```bash
vendor/bin/phpstan analyse src/ --memory-limit=512M
```

Or add to `phpstan.neon`:
```yaml
parameters:
    parallel:
        maximumNumberOfProcesses: 1
```

---

### Circuit breaker keeps opening

**Cause:** Persistent downstream failures keep tripping the circuit breaker.

**Fix:** Check the actual error, verify the provider API status, or temporarily increase the `failureThreshold` / reduce `cooldownSeconds` for debugging:

```php
new CircuitBreakerMiddleware(failureThreshold: 20, cooldownSeconds: 5);
```

---

### Streaming response is empty

**Cause:** Some providers require `stream_options.include_usage = true` to get a proper final chunk.

**Fix:**
```php
NexusAI::using('openai', 'gpt-4o')
    ->withOption('stream_options', ['include_usage' => true])
    ->withPrompt('...')
    ->asStream();
```

---

## Getting Help

- [Full Documentation](https://github.com/token27/nexus-ai/tree/main/docs)
- [Report a Bug](https://github.com/token27/nexus-ai/issues/new?template=bug_report.md)
- [Request a Feature](https://github.com/token27/nexus-ai/issues/new?template=feature_request.md)
- [Discussions](https://github.com/token27/nexus-ai/discussions)

---

> **← Back:** [Testing](testing.md) · **Next:** [Contributing →](contributing.md)
