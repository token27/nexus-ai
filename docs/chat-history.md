# Chat History

NexusAI provides two `ChatHistoryInterface` implementations for managing multi-turn conversation state across requests.

## Why Use Chat History?

In web applications, each HTTP request is stateless — you need to persist the conversation manually. Chat History implementations handle storage, retrieval, FIFO eviction, and token-limit trimming for you.

## InMemoryChatHistory

Lives in PHP memory. Lost when the process ends. Best for CLI scripts, queue workers, or single-request flows.

```php
use Token27\NexusAI\History\InMemoryChatHistory;
use Token27\NexusAI\Message\{UserMessage, AssistantMessage};

$history = new InMemoryChatHistory(maxMessages: 20);

$history->addMessage(new UserMessage('Hello!'));
$history->addMessage(new AssistantMessage('Hi there! How can I help?'));
$history->addMessage(new UserMessage('What is PHP?'));

// Use in a request
$response = NexusAI::using('openai', 'gpt-4o')
    ->withMessages($history->getMessages())
    ->withPrompt('Can you give me an example?')
    ->asText();

// Store the reply
$history->addMessage(new AssistantMessage($response->text));
```

### Constructor Options

```php
new InMemoryChatHistory(
    maxMessages: 50, // null = unlimited. Oldest non-system messages evicted first.
);
```

### System Message Protection

`SystemMessage` objects are **never evicted** — even when `maxMessages` is exceeded:

```php
use Token27\NexusAI\Message\SystemMessage;

$history = new InMemoryChatHistory(maxMessages: 3);
$history->addMessage(new SystemMessage('You are a PHP expert.'));
$history->addMessage(new UserMessage('Q1'));
$history->addMessage(new AssistantMessage('A1'));
$history->addMessage(new UserMessage('Q2')); // This evicts 'Q1', not the SystemMessage

// Count: SystemMessage + AssistantMessage('A1') + UserMessage('Q2') = 3
```

### Token-based Trimming

```php
// Trim to ~4000 tokens (rough estimate: strlen / 4)
$history->trimToTokenLimit(4000);
```

## FileChatHistory

Persists messages as JSON on disk. Survives between HTTP requests. Uses `LOCK_EX` for concurrent safety.

```php
use Token27\NexusAI\History\FileChatHistory;

// Path is created automatically if it doesn't exist
$history = new FileChatHistory('/var/app/chats/user-123.json');

$history->addMessage(new UserMessage('What time is it?'));
$history->addMessage(new AssistantMessage('I don\'t have real-time data.'));

// Next HTTP request — history is still there
$history2 = new FileChatHistory('/var/app/chats/user-123.json');
echo $history2->count(); // 2
```

### Multi-user Pattern

```php
// Each user or session gets their own file
$userId = auth()->id();
$history = new FileChatHistory("/var/app/chats/{$userId}.json");
```

### Session-based (Laravel example)

```php
$sessionId = session()->getId();
$history = new FileChatHistory(storage_path("chats/{$sessionId}.json"));
```

## Full API Reference

Both implementations share the same `ChatHistoryInterface`:

```php
$history->addMessage(Message $message): void;
$history->getMessages(): array;          // All messages
$history->getLastMessage(): ?Message;    // Latest message
$history->count(): int;                  // Message count
$history->flush(): void;                 // Clear all messages
$history->slice(int $offset, ?int $length): array; // Pagination
```

`InMemoryChatHistory` also provides:
```php
$history->trimToTokenLimit(int $maxTokens): void;
```

## Complete Chatbot Example

```php
use Token27\NexusAI\History\FileChatHistory;
use Token27\NexusAI\Message\{SystemMessage, UserMessage, AssistantMessage};
use Token27\NexusAI\NexusAI;

function chat(string $userId, string $userInput): string
{
    $history = new FileChatHistory("/var/app/chats/{$userId}.json");

    // Add system prompt only on first message
    if ($history->count() === 0) {
        $history->addMessage(new SystemMessage(
            'You are a helpful PHP assistant. Be concise and practical.'
        ));
    }

    // Add user message
    $history->addMessage(new UserMessage($userInput));

    // Trim if getting long (~3000 tokens)
    // (Note: InMemoryChatHistory has trimToTokenLimit, FileChatHistory doesn't)

    // Send full conversation
    $response = NexusAI::using('openai', 'gpt-4o')
        ->withMessages($history->getMessages())
        ->withMaxTokens(500)
        ->asText();

    // Store response
    $history->addMessage(new AssistantMessage($response->text));

    return $response->text;
}

// Usage
echo chat('user-42', 'What is a closure in PHP?');
echo chat('user-42', 'Can you show me an example?'); // remembers context!
```

## Choosing the Right Implementation

| | `InMemoryChatHistory` | `FileChatHistory` |
|---|---|---|
| **Persistence** | None (in-memory) | JSON file on disk |
| **Best for** | CLI, workers, single-request | Web apps, multi-request |
| **Token trimming** | ✅ `trimToTokenLimit()` | ❌ Manual |
| **Concurrent safe** | ✅ (single process) | ✅ (`LOCK_EX`) |
| **Custom storage** | Implement `ChatHistoryInterface` | Same |

For Redis, database, or custom backends — implement `ChatHistoryInterface` directly.

---

> **← Back:** [Tool Calling](tool-calling.md) · **Next:** [Middleware Pipeline →](middleware.md)
