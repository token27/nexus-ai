<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\StructuredOutput\SchemaGenerator;
use Token27\NexusAI\StructuredOutput\SchemaProperty;

final class SchemaGeneratorTest extends TestCase
{
    public function testGeneratesSchemaFromSimpleDto(): void
    {
        $schema = SchemaGenerator::generate(SimpleDto::class);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('integer', $schema['properties']['age']['type']);
    }

    public function testRequiredProperties(): void
    {
        $schema = SchemaGenerator::generate(SimpleDto::class);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('age', $schema['required']);
    }

    public function testNullableProperty(): void
    {
        $schema = SchemaGenerator::generate(NullableDto::class);

        $type = $schema['properties']['nickname']['type'];
        $this->assertIsArray($type);
        $this->assertContains('string', $type);
        $this->assertContains('null', $type);
    }

    public function testSchemaPropertyDescription(): void
    {
        $schema = SchemaGenerator::generate(AnnotatedDto::class);

        $this->assertSame('The article title', $schema['properties']['title']['description']);
    }

    public function testAdditionalPropertiesFalse(): void
    {
        $schema = SchemaGenerator::generate(SimpleDto::class);
        $this->assertFalse($schema['additionalProperties']);
    }
}

// Test DTOs (defined in same file for simplicity)
class SimpleDto
{
    public string $name;

    public int $age;
}

class NullableDto
{
    public ?string $nickname = null;
}

class AnnotatedDto
{
    #[SchemaProperty(description: 'The article title')]
    public string $title;
}
