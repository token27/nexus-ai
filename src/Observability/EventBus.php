<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability;

use Token27\NexusAI\Contract\ObserverInterface;

/**
 * Bus de eventos that distributes system events to registered observers.
 *
 * Unlike Neuron AI's static singleton EventBus with workflow scoping and
 * Inspector APM coupling, NexusAI's EventBus is a simple instance-based
 * class with synchronous emission and no external dependencies.
 *
 * Critical design rule: emit() MUST silently catch and log observer errors.
 * An observer failure must NEVER break the main request flow.
 *
 * @see \Token27\NexusAI\Contract\ObserverInterface
 */
final class EventBus
{
    /** @var array<ObserverInterface> Registered observers. */
    private array $observers = [];

    /**
     * Registers an observer to receive events.
     *
     * @param ObserverInterface $observer The observer to register.
     */
    public function subscribe(ObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    /**
     * Removes a previously registered observer.
     *
     * @param ObserverInterface $observer The observer to remove.
     */
    public function unsubscribe(ObserverInterface $observer): void
    {
        $this->observers = array_values(
            array_filter(
                $this->observers,
                fn (ObserverInterface $o): bool => $o !== $observer,
            ),
        );
    }

    /**
     * Emits an event to ALL registered observers.
     *
     * Errors from observers are silently caught and logged to error_log.
     * This ensures the main request flow is NEVER interrupted by observer failures.
     *
     * @param string $event The event name (e.g., 'request.started', 'request.completed').
     * @param object $source The object that emitted the event (e.g., Pipeline, Driver).
     * @param mixed $data Optional event data DTO with event-specific information.
     */
    public function emit(string $event, object $source, mixed $data = null): void
    {
        foreach ($this->observers as $observer) {
            try {
                $observer->onEvent($event, $source, $data);
            } catch (\Throwable $e) {
                // Silently log — NEVER break the main flow
                error_log(sprintf(
                    'NexusAI observer error [%s]: %s in %s:%d',
                    $observer::class,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ));
            }
        }
    }

    /**
     * Returns all registered observers.
     *
     * @return array<ObserverInterface> The list of registered observers.
     */
    public function getObservers(): array
    {
        return $this->observers;
    }
}
