<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Message\Message;

/**
 * Storage contract for conversation history.
 *
 * Allows persisting and retrieving messages between calls. Implementations
 * can store messages in memory, files, cache pools, or databases.
 *
 * Inspired by Neuron AI's ChatHistoryInterface with added slice() for context trimming.
 *
 * @see \Token27\NexusAI\History\InMemoryChatHistory
 */
interface ChatHistoryInterface
{
    /**
     * Adds a message to the history.
     *
     * @param Message $message The message to store.
     */
    public function addMessage(Message $message): void;

    /**
     * Returns all messages in chronological order.
     *
     * @return array<Message> All stored messages.
     */
    public function getMessages(): array;

    /**
     * Returns the last message, or null if the history is empty.
     *
     * @return Message|null The most recent message.
     */
    public function getLastMessage(): ?Message;

    /**
     * Returns the number of stored messages.
     *
     * @return int Message count.
     */
    public function count(): int;

    /**
     * Clears all messages from the history.
     */
    public function flush(): void;

    /**
     * Returns a subset of messages for context trimming.
     *
     * @param int $offset Starting position (0-based).
     * @param int|null $length Number of messages to return, or null for all remaining.
     * @return array<Message> The sliced messages.
     */
    public function slice(int $offset, ?int $length = null): array;
}
