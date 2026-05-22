<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Tool;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Tool\Tool;
use Token27\NexusAI\Tool\ToolExecutor;
use Token27\NexusAI\Tool\ToolRegistry;
use Token27\NexusAI\ValueObject\ToolCall;

final class ToolExecutorTest extends TestCase
{
    public function testExecutesSingleTool(): void
    {
        $registry = new ToolRegistry();
        $tool = Tool::make('greet', 'Greets a user')
            ->setCallable(fn (array $args): string => "Hello, {$args['name']}!");
        $registry->register($tool);

        $executor = new ToolExecutor($registry);

        $results = $executor->execute([
            new ToolCall(id: 'c1', name: 'greet', arguments: ['name' => 'Alice']),
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Hello, Alice!', $results[0]->result);
        $this->assertFalse($results[0]->isError);
        $this->assertSame('c1', $results[0]->callId);
    }

    public function testReturnsErrorForUnknownTool(): void
    {
        $registry = new ToolRegistry();
        $executor = new ToolExecutor($registry);

        $results = $executor->execute([
            new ToolCall(id: 'c2', name: 'unknown_tool', arguments: []),
        ]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isError);
        $this->assertStringContainsString('not found', $results[0]->result);
    }

    public function testCatchesToolExceptions(): void
    {
        $registry = new ToolRegistry();
        $tool = Tool::make('crasher', 'Always crashes')
            ->setCallable(function (array $args): string {
                throw new \RuntimeException('Boom!');
            });
        $registry->register($tool);

        $executor = new ToolExecutor($registry);

        $results = $executor->execute([
            new ToolCall(id: 'c3', name: 'crasher', arguments: []),
        ]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isError);
        $this->assertStringContainsString('Boom!', $results[0]->result);
    }

    public function testExecutesMultipleTools(): void
    {
        $registry = new ToolRegistry();
        $registry->register(Tool::make('a', 'A')->setCallable(fn (array $a): string => 'resA'));
        $registry->register(Tool::make('b', 'B')->setCallable(fn (array $a): string => 'resB'));

        $executor = new ToolExecutor($registry);

        $results = $executor->execute([
            new ToolCall(id: 'c1', name: 'a', arguments: []),
            new ToolCall(id: 'c2', name: 'b', arguments: []),
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('resA', $results[0]->result);
        $this->assertSame('resB', $results[1]->result);
    }
}
