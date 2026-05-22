<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Exception\StructuredOutputException;
use Token27\NexusAI\StructuredOutput\Deserializer;

final class DeserializerTest extends TestCase
{
    public function testDeserializeSimpleDto(): void
    {
        $json = '{"name": "Alice", "age": 30}';
        $obj = Deserializer::deserialize($json, DeserializerSimpleDto::class);

        $this->assertInstanceOf(DeserializerSimpleDto::class, $obj);
        $this->assertSame('Alice', $obj->name);
        $this->assertSame(30, $obj->age);
    }

    public function testDeserializeWithNullable(): void
    {
        $json = '{"value": null}';
        $obj = Deserializer::deserialize($json, DeserializerNullableDto::class);

        $this->assertNull($obj->value);
    }

    public function testDeserializeTypeCasting(): void
    {
        $json = '{"count": "42", "rate": "3.14", "active": true}';
        $obj = Deserializer::deserialize($json, DeserializerTypedDto::class);

        $this->assertSame(42, $obj->count);
        $this->assertEqualsWithDelta(3.14, $obj->rate, 0.001);
        $this->assertTrue($obj->active);
    }

    public function testInvalidJsonThrows(): void
    {
        $this->expectException(StructuredOutputException::class);
        Deserializer::deserialize('not json', DeserializerSimpleDto::class);
    }

    public function testNonExistentClassThrows(): void
    {
        $this->expectException(StructuredOutputException::class);
        Deserializer::deserialize('{"a": 1}', 'NonExistent\\Class');
    }
}

class DeserializerSimpleDto
{
    public string $name;

    public int $age;
}

class DeserializerNullableDto
{
    public ?string $value = null;
}

class DeserializerTypedDto
{
    public int $count;

    public float $rate;

    public bool $active;
}
