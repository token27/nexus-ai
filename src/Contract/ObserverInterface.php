<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

/**
 * Receptor of system events for monitoring, logging, metrics, and APM.
 *
 * Observers are registered with the EventBus and receive notifications
 * about request lifecycle events. Inspired by Neuron AI's ObserverInterface
 * but simplified (no $branchId since NexusAI v1 has no workflow/branching).
 *
 * @see \Token27\NexusAI\Observability\EventBus
 */
interface ObserverInterface
{
    /**
     * Receives a system event.
     *
     * @param string $event The event name (e.g., 'request.started', 'request.completed').
     * @param object $source The object that emitted the event (e.g., Pipeline, Driver).
     * @param mixed $data Optional event data DTO with event-specific information.
     */
    public function onEvent(string $event, object $source, mixed $data = null): void;
}
