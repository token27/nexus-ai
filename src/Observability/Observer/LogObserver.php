<?php

declare(strict_types=1);

namespace Token27\NexusAI\Observability\Observer;

use Psr\Log\LoggerInterface;
use Token27\NexusAI\Contract\ObserverInterface;
use Token27\NexusAI\Observability\Event\RequestCompleted;
use Token27\NexusAI\Observability\Event\RequestFailed;
use Token27\NexusAI\Observability\Event\RequestStarted;
use Token27\NexusAI\Observability\Event\StreamChunkReceived;
use Token27\NexusAI\Observability\Event\ToolCalled;
use Token27\NexusAI\Observability\Event\ToolCalling;

/**
 * Observer that routes all system events to a PSR-3 Logger.
 *
 * Serializes each event DTO into a PSR-3 context array appropriate
 * for its type. Inspired by Neuron AI's LogObserver but without
 * Inspector APM coupling.
 *
 * @see \Token27\NexusAI\Contract\ObserverInterface
 * @see \Token27\NexusAI\Observability\EventBus
 */
final class LogObserver implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param string $level Log level for event messages (default: 'info').
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = 'info',
    ) {
    }

    /**
     * Receives a system event and logs it via PSR-3.
     *
     * Uses match() on the data class to format each event DTO
     * into an appropriate context array.
     *
     * @param string $event The event name.
     * @param object $source The object that emitted the event.
     * @param mixed $data Optional event data DTO.
     */
    public function onEvent(string $event, object $source, mixed $data = null): void
    {
        $context = $this->buildContext($data);
        $context['source'] = $source::class;

        $this->logger->log($this->level, sprintf('NexusAI [%s]', $event), $context);
    }

    /**
     * Builds a PSR-3 context array from the event data DTO.
     *
     * @param mixed $data The event data DTO.
     * @return array<string, mixed> PSR-3 context array.
     */
    private function buildContext(mixed $data): array
    {
        if ($data === null) {
            return [];
        }

        if (!is_object($data)) {
            return ['data' => $data];
        }

        return match ($data::class) {
            RequestStarted::class => [
                'provider' => $data->request->getProvider(),
                'model' => $data->request->getModel(),
                'timestamp' => $data->timestamp,
            ],

            RequestCompleted::class => [
                'provider' => $data->request->getProvider(),
                'model' => $data->request->getModel(),
                'elapsed_ms' => $data->elapsedMs,
                'tokens' => $data->usage?->totalTokens(),
                'cost' => $data->pricingResult?->totalCostUsd(),
                'finish_reason' => $data->response->getFinishReason()->value,
            ],

            RequestFailed::class => [
                'provider' => $data->request->getProvider(),
                'model' => $data->request->getModel(),
                'elapsed_ms' => $data->elapsedMs,
                'error' => $data->exception->getMessage(),
                'exception_class' => $data->exception::class,
            ],

            ToolCalling::class => [
                'tool' => $data->toolName,
                'arguments' => $data->arguments,
            ],

            ToolCalled::class => [
                'tool' => $data->toolName,
                'elapsed_ms' => $data->elapsedMs,
                'is_error' => $data->isError,
                'result_length' => strlen($data->result),
            ],

            StreamChunkReceived::class => [
                'index' => $data->index,
                'type' => $data->chunk->getType(),
                'has_text' => $data->chunk->getText() !== null,
            ],

            default => ['data_class' => $data::class],
        };
    }
}
