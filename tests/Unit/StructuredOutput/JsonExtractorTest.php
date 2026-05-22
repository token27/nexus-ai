<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Exception\StructuredOutputException;
use Token27\NexusAI\StructuredOutput\JsonExtractor;

final class JsonExtractorTest extends TestCase
{
    public function testDirectJson(): void
    {
        $json = '{"name": "John", "age": 30}';
        $this->assertSame($json, JsonExtractor::extract($json));
    }

    public function testExtractFromMarkdownBlock(): void
    {
        $text = "Here is the result:\n```json\n{\"title\": \"Hello\"}\n```\nDone.";
        $result = JsonExtractor::extract($text);
        $decoded = json_decode($result, true);
        $this->assertSame('Hello', $decoded['title']);
    }

    public function testExtractFromBraces(): void
    {
        $text = 'Some preamble text {"key": "value"} trailing text';
        $result = JsonExtractor::extract($text);
        $decoded = json_decode($result, true);
        $this->assertSame('value', $decoded['key']);
    }

    public function testExtractArray(): void
    {
        $text = 'Result: [1, 2, 3]';
        $result = JsonExtractor::extract($text);
        $decoded = json_decode($result, true);
        $this->assertSame([1, 2, 3], $decoded);
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(StructuredOutputException::class);
        JsonExtractor::extract('');
    }

    public function testNoJsonThrows(): void
    {
        $this->expectException(StructuredOutputException::class);
        JsonExtractor::extract('This is just plain text with no JSON at all.');
    }

    public function testPureJsonArray(): void
    {
        $json = '[{"a": 1}, {"b": 2}]';
        $this->assertSame($json, JsonExtractor::extract($json));
    }
}
