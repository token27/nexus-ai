# Text Generation

## Basic Usage

```php
$response = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt('What is the capital of France?')
    ->asText();

echo $response->text;          // "The capital of France is Paris."
echo $response->finishReason;  // FinishReason::Stop
echo $response->usage->totalTokens(); // 42
```

## System Prompt

```php
$response = NexusAI::using('anthropic', 'claude-sonnet-4-20250514')
    ->withSystemPrompt('You are a senior PHP engineer. Be concise.')
    ->withPrompt('Explain readonly properties in PHP 8.1.')
    ->asText();
```

## Conversation History

```php
use Token27\NexusAI\Message\{UserMessage, AssistantMessage, SystemMessage};

$response = NexusAI::using('openai', 'gpt-4o')
    ->withMessages([
        new SystemMessage('You are a helpful tutor.'),
        new UserMessage('What is a closure in PHP?'),
        new AssistantMessage('A closure is an anonymous function that can capture variables from its surrounding scope.'),
        new UserMessage('Can you show me an example?'),
    ])
    ->asText();
```

## Request Options

Options are passed directly to the provider API:

```php
$response = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt('Write a haiku about PHP.')
    ->withOption('temperature', 0.9)
    ->withOption('max_tokens', 100)
    ->withOption('top_p', 0.95)
    ->asText();
```

## Multi-modal (Vision)

```php
use Token27\NexusAI\ValueObject\ContentBlock;
use Token27\NexusAI\Message\UserMessage;

$response = NexusAI::using('openai', 'gpt-4o')
    ->withMessages([
        new UserMessage([
            ContentBlock::text('What is in this image?'),
            ContentBlock::imageUrl('https://example.com/chart.png'),
        ]),
    ])
    ->asText();
```

## Response Object

```php
$response->text;          // string — the generated text
$response->finishReason;  // FinishReason enum (Stop, Length, ToolCalls, ContentFilter, Unknown)
$response->usage;         // Usage (promptTokens, completionTokens, totalTokens())
$response->getMeta();     // Meta (id, model, rateLimits)
```

---

> **← Back:** [Configuration](configuration.md) · **Next:** [Structured Output →](structured-output.md)
