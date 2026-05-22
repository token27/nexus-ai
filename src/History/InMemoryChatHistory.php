<?php

declare(strict_types=1);

namespace Token27\NexusAI\History;

use Token27\NexusAI\Contract\ChatHistoryInterface;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\SystemMessage;

/**
 * In-memory implementation of ChatHistoryInterface.
 *
 * Stores messages in a PHP array. The history is lost when the process ends.
 * Supports an optional maxMessages limit (FIFO eviction) and token-based trimming.
 *
 * Inspired by Neuron AI's InMemoryChatHistory but simplified — no abstract base
 * class, no trimmer dependency. Token estimation uses a simple strlen/4 heuristic.
 *
 * @see \Token27\NexusAI\Contract\ChatHistoryInterface
 */
final class InMemoryChatHistory implements ChatHistoryInterface
{
    /** @var array<Message> Chronologically ordered message queue. */
    private array $messages = [];

    /**
     * @param int|null $maxMessages Maximum number of messages to retain. Null for unlimited.
     *                              When exceeded, oldest non-system messages are removed (FIFO).
     */
    public function __construct(
        private readonly ?int $maxMessages = null,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Adds a message and enforces the maxMessages limit via FIFO eviction.
     * SystemMessages are protected from eviction.
     */
    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;

        // Enforce maxMessages limit
        if ($this->maxMessages !== null) {
            while (count($this->messages) > $this->maxMessages) {
                // Find the first non-system message to remove
                $removed = false;
                foreach ($this->messages as $index => $msg) {
                    if (!($msg instanceof SystemMessage)) {
                        array_splice($this->messages, $index, 1);
                        $removed = true;
                        break;
                    }
                }

                // Safety: if all messages are system messages, stop trying to remove
                if (!$removed) {
                    break;
                }
            }
        }
    }

    /** {@inheritdoc} */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** {@inheritdoc} */
    public function getLastMessage(): ?Message
    {
        if ($this->messages === []) {
            return null;
        }

        return $this->messages[array_key_last($this->messages)];
    }

    /** {@inheritdoc} */
    public function count(): int
    {
        return count($this->messages);
    }

    /** {@inheritdoc} */
    public function flush(): void
    {
        $this->messages = [];
    }

    /** {@inheritdoc} */
    public function slice(int $offset, ?int $length = null): array
    {
        return array_slice($this->messages, $offset, $length);
    }

    /**
     * Trims messages to fit within a token limit.
     *
     * Removes the oldest non-system messages until the estimated total tokens
     * is at or below the specified limit. Token estimation uses a simple
     * heuristic of strlen/4 (approximately 1 token per 4 characters).
     *
     * IMPORTANT: SystemMessages are NEVER removed, even if they are the oldest.
     *
     * @param int $maxTokens Maximum allowed token count.
     */
    public function trimToTokenLimit(int $maxTokens): void
    {
        while ($this->estimateTotalTokens() > $maxTokens) {
            // Find the first non-system message to remove
            $removed = false;
            foreach ($this->messages as $index => $message) {
                if (!($message instanceof SystemMessage)) {
                    array_splice($this->messages, $index, 1);
                    $removed = true;
                    break;
                }
            }

            // If only system messages remain, stop — never remove them
            if (!$removed) {
                break;
            }
        }
    }

    /**
     * Estimates the total token count for all messages.
     *
     * Uses a simple heuristic: strlen(text) / 4 ≈ tokens.
     * This is a rough approximation; for precise counting, use a tokenizer library.
     *
     * @return int Estimated total tokens across all messages.
     */
    private function estimateTotalTokens(): int
    {
        $total = 0;

        foreach ($this->messages as $message) {
            $text = $message->getText();
            $total += (int) ceil(strlen($text) / 4);
        }

        return $total;
    }
}
