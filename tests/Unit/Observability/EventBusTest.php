<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Contract\ObserverInterface;
use Token27\NexusAI\Observability\EventBus;

final class EventBusTest extends TestCase
{
    public function testSubscribeAndEmit(): void
    {
        $bus = new EventBus();
        $received = [];

        $observer = new class ($received) implements ObserverInterface {
            public function __construct(private array &$received)
            {
            }

            public function onEvent(string $event, object $source, mixed $data = null): void
            {
                $this->received[] = ['event' => $event, 'data' => $data];
            }
        };

        $bus->subscribe($observer);
        $bus->emit('test.event', $this, ['key' => 'value']);

        $this->assertCount(1, $received);
        $this->assertSame('test.event', $received[0]['event']);
    }

    public function testUnsubscribe(): void
    {
        $bus = new EventBus();
        $count = 0;

        $observer = new class ($count) implements ObserverInterface {
            public function __construct(private int &$count)
            {
            }

            public function onEvent(string $event, object $source, mixed $data = null): void
            {
                $this->count++;
            }
        };

        $bus->subscribe($observer);
        $bus->emit('e1', $this);
        $this->assertSame(1, $count);

        $bus->unsubscribe($observer);
        $bus->emit('e2', $this);
        $this->assertSame(1, $count); // Not incremented
    }

    public function testObserverErrorDoesNotBreakFlow(): void
    {
        $bus = new EventBus();
        $secondCalled = false;

        $crasher = new class () implements ObserverInterface {
            public function onEvent(string $event, object $source, mixed $data = null): void
            {
                throw new \RuntimeException('Observer crash!');
            }
        };

        $healthy = new class ($secondCalled) implements ObserverInterface {
            public function __construct(private bool &$called)
            {
            }

            public function onEvent(string $event, object $source, mixed $data = null): void
            {
                $this->called = true;
            }
        };

        $bus->subscribe($crasher);
        $bus->subscribe($healthy);
        $bus->emit('test', $this);

        $this->assertTrue($secondCalled, 'Second observer must still be called');
    }

    public function testGetObservers(): void
    {
        $bus = new EventBus();
        $this->assertSame([], $bus->getObservers());

        $obs = new class () implements ObserverInterface {
            public function onEvent(string $event, object $source, mixed $data = null): void
            {
            }
        };

        $bus->subscribe($obs);
        $this->assertCount(1, $bus->getObservers());
    }
}
