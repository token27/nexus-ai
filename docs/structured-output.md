# Structured Output

Force the LLM to return data that maps directly to a PHP DTO.

## Define a DTO

Use typed public properties. NexusAI uses reflection to generate the JSON Schema automatically.

```php
use Token27\NexusAI\StructuredOutput\SchemaProperty;

class ProductReview
{
    #[SchemaProperty(description: 'Product name mentioned in the review')]
    public string $productName;

    #[SchemaProperty(description: 'Rating from 1 to 5')]
    public int $rating;

    #[SchemaProperty(description: 'One-sentence summary of the review')]
    public string $summary;

    #[SchemaProperty(description: 'List of pros mentioned')]
    /** @var string[] */
    public array $pros;

    #[SchemaProperty(description: 'Is the reviewer likely to recommend this product?')]
    public bool $recommended;

    public ?string $notes = null; // nullable — optional field
}
```

## Request Structured Output

```php
$review = NexusAI::using('openai', 'gpt-4o')
    ->withSystemPrompt('Extract structured product review data from the text.')
    ->withPrompt('I bought the Sony WH-1000XM5. Amazing noise cancellation, great battery life. Sound is a bit bassy for my taste. 4/5, would recommend.')
    ->asStructured(ProductReview::class);

echo $review->productName;  // "Sony WH-1000XM5"
echo $review->rating;       // 4
echo $review->recommended;  // true
```

## Supported Types

| PHP Type | JSON Schema | Notes |
|----------|-------------|-------|
| `string` | `string` | |
| `int` | `integer` | |
| `float` | `number` | |
| `bool` | `boolean` | |
| `array` | `array` | Use `@var string[]` for typed arrays |
| `?string` | `["string","null"]` | Nullable |
| `MyEnum` | `string` (enum values) | Must be `BackedEnum` |
| `NestedDto` | `object` | Recursive schema generation |
| `DateTimeImmutable` | `string` (date-time) | Auto-hydrated |

## Validation with Attributes

```php
use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MinLength implements ValidationRuleInterface
{
    public function __construct(private int $min) {}

    public function validate(mixed $value, string $propertyName): ?string
    {
        if (strlen((string) $value) < $this->min) {
            return "{$propertyName} must be at least {$this->min} characters.";
        }
        return null;
    }
}

class Article
{
    #[MinLength(10)]
    public string $title;
}
```

Validation runs automatically after deserialization. On failure, a `StructuredOutputException` is thrown with the list of violations.

## Error Handling

```php
use Token27\NexusAI\Exception\StructuredOutputException;

try {
    $result = NexusAI::using('openai', 'gpt-4o')
        ->withPrompt('...')
        ->asStructured(MyDto::class);
} catch (StructuredOutputException $e) {
    echo $e->getMessage();
    echo $e->responseText;  // Raw LLM output that failed
}
```

---

> **← Back:** [Text Generation](text-generation.md) · **Next:** [Streaming →](streaming.md)
