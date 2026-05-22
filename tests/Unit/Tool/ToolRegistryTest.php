<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Tool;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Exception\ToolException;
use Token27\NexusAI\Tool\PropertyType;
use Token27\NexusAI\Tool\Tool;
use Token27\NexusAI\Tool\ToolProperty;
use Token27\NexusAI\Tool\ToolRegistry;

final class ToolRegistryTest extends TestCase
{
    private function makeTool(string $name): Tool
    {
        return Tool::make($name, "Description for {$name}")
            ->setCallable(fn (array $args): string => 'result');
    }

    public function testRegisterAndGet(): void
    {
        $registry = new ToolRegistry();
        $tool = $this->makeTool('test_tool');
        $registry->register($tool);

        $this->assertTrue($registry->has('test_tool'));
        $this->assertSame($tool, $registry->get('test_tool'));
    }

    public function testGetThrowsForUnknownTool(): void
    {
        $registry = new ToolRegistry();

        $this->expectException(ToolException::class);
        $registry->get('unknown');
    }

    public function testRegisterMany(): void
    {
        $registry = new ToolRegistry();
        $t1 = $this->makeTool('tool_a');
        $t2 = $this->makeTool('tool_b');

        $registry->registerMany([$t1, $t2]);

        $this->assertTrue($registry->has('tool_a'));
        $this->assertTrue($registry->has('tool_b'));
        $this->assertCount(2, $registry->all());
    }

    public function testToSchemaArray(): void
    {
        $registry = new ToolRegistry();
        $tool = Tool::make('get_weather', 'Gets weather for a city')
            ->addProperty(new ToolProperty('city', PropertyType::String, 'City name', required: true))
            ->setCallable(fn (array $args): string => 'sunny');

        $registry->register($tool);

        $schemas = $registry->toSchemaArray();

        $this->assertCount(1, $schemas);
        $this->assertSame('function', $schemas[0]['type']);
        $this->assertSame('get_weather', $schemas[0]['function']['name']);
        $this->assertSame('Gets weather for a city', $schemas[0]['function']['description']);
    }

    public function testHasReturnsFalseForUnregistered(): void
    {
        $registry = new ToolRegistry();
        $this->assertFalse($registry->has('nonexistent'));
    }
}
