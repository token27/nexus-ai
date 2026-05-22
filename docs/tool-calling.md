# Tool Calling

## Define a Tool

```php
use Token27\NexusAI\Tool\Tool;
use Token27\NexusAI\Tool\ToolProperty;
use Token27\NexusAI\Tool\PropertyType;

$weather = Tool::make('get_weather', 'Gets current weather for a city')
    ->addProperty(new ToolProperty('city', PropertyType::String, 'City name', required: true))
    ->addProperty(new ToolProperty('unit', PropertyType::String, 'celsius or fahrenheit', required: false))
    ->setCallable(function (array $args): string {
        $unit = $args['unit'] ?? 'celsius';
        return json_encode(['city' => $args['city'], 'temp' => 22, 'unit' => $unit]);
    });
```

## Use Tools in a Request

```php
$response = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt('What is the weather in Madrid and London?')
    ->withTools([$weather])
    ->asText();

echo $response->text; // "Madrid is 22°C. London is 15°C."
```

The driver automatically handles the multi-step loop: LLM → tool calls → results → final response.

## Supported Property Types

| `PropertyType` | JSON Schema | PHP |
|----------------|-------------|-----|
| `String` | `string` | `string` |
| `Integer` | `integer` | `int` |
| `Number` | `number` | `float` |
| `Boolean` | `boolean` | `bool` |
| `Array` | `array` | `array` |
| `Object` | `object` | `array` |

## ToolRegistry

Register tools centrally and reuse across requests:

```php
use Token27\NexusAI\Tool\ToolRegistry;

$registry = new ToolRegistry();
$registry->register($weather);
$registry->register($search);

// Get schema for API payload
$schemas = $registry->toSchemaArray();
```

## ToolExecutor

Execute tool calls returned by the LLM:

```php
use Token27\NexusAI\Tool\ToolExecutor;

$executor = new ToolExecutor($registry);

$results = $executor->execute($toolCalls); // array<ToolCall>

foreach ($results as $result) {
    echo $result->toolName . ': ' . $result->result;
    echo $result->isError ? ' (ERROR)' : ' (OK)';
}
```

## Error Handling in Tools

If a tool callable throws an exception, `ToolExecutor` catches it and returns a `ToolResult` with `isError: true` — the LLM sees the error message and can decide how to proceed.

```php
->setCallable(function (array $args): string {
    if (empty($args['city'])) {
        throw new \InvalidArgumentException('City is required');
    }
    // ...
});
```

---

> **← Back:** [Embeddings](embeddings.md) · **Next:** [Chat History →](chat-history.md)
