<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Tool;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Tool\PropertyType;
use Token27\NexusAI\Tool\Tool;
use Token27\NexusAI\Tool\ToolProperty;

final class ToolTest extends TestCase
{
    public function testMakeFactoryCreatesWorkingTool(): void
    {
        $tool = Tool::make('calculator', 'Does math')
            ->addProperty(new ToolProperty('expression', PropertyType::String, 'Math expression', required: true))
            ->setCallable(fn (array $args): string => (string) eval("return {$args['expression']};"));

        $this->assertSame('calculator', $tool->getName());
        $this->assertSame('Does math', $tool->getDescription());
    }

    public function testGetParametersReturnsSchema(): void
    {
        $tool = Tool::make('test', 'Test tool')
            ->addProperty(new ToolProperty('name', PropertyType::String, 'Name', required: true))
            ->addProperty(new ToolProperty('count', PropertyType::Integer, 'Count', required: false));

        $params = $tool->getParameters();

        $this->assertSame('object', $params['type']);
        $this->assertArrayHasKey('name', $params['properties']);
        $this->assertArrayHasKey('count', $params['properties']);
        $this->assertContains('name', $params['required']);
        $this->assertNotContains('count', $params['required']);
    }

    public function testEmptyPropertiesReturnsEmptyArray(): void
    {
        $tool = Tool::make('simple', 'No params')
            ->setCallable(fn (array $args): string => 'done');

        $this->assertSame([], $tool->getParameters());
    }

    public function testExecuteWithCallable(): void
    {
        $tool = Tool::make('echo', 'Echoes input')
            ->setCallable(fn (array $args): string => $args['text'] ?? 'empty');

        $this->assertSame('hello', $tool->execute(['text' => 'hello']));
    }

    public function testExecuteWithoutCallableThrows(): void
    {
        $tool = Tool::make('broken', 'No callable set');

        $this->expectException(\BadMethodCallException::class);
        $tool->execute([]);
    }
}
