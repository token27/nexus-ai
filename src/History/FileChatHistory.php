<?php

declare(strict_types=1);

namespace Token27\NexusAI\History;

use Token27\NexusAI\Contract\ChatHistoryInterface;
use Token27\NexusAI\Enum\MessageRole;
use Token27\NexusAI\Message\AssistantMessage;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\SystemMessage;
use Token27\NexusAI\Message\UserMessage;

/**
 * File-based implementation of ChatHistoryInterface.
 *
 * Persists messages as JSON in a file on disk. Survives between PHP requests.
 * Uses LOCK_EX for file locking to handle concurrent access safely.
 *
 * Messages are serialized as JSON arrays and deserialized back into Message objects.
 * Supports SystemMessage, UserMessage, and AssistantMessage types.
 *
 * Inspired by Neuron AI's FileChatHistory but simplified — no abstract base,
 * no trimmer, direct JSON serialization of NexusAI Message types.
 *
 * @see \Token27\NexusAI\Contract\ChatHistoryInterface
 */
final class FileChatHistory implements ChatHistoryInterface
{
    /**
     * @param string $filePath Absolute path to the JSON file for storing messages.
     */
    public function __construct(
        private readonly string $filePath,
    ) {
        // Ensure the directory exists
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Create the file with an empty array if it doesn't exist
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, '[]', LOCK_EX);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Reads the file, appends the message, and rewrites the entire file with LOCK_EX.
     */
    public function addMessage(Message $message): void
    {
        $messages = $this->loadMessages();
        $messages[] = $this->serializeMessage($message);

        $this->saveMessages($messages);
    }

    /** {@inheritdoc} */
    public function getMessages(): array
    {
        $raw = $this->loadMessages();

        return array_map(
            fn (array $data): Message => $this->deserializeMessage($data),
            $raw,
        );
    }

    /** {@inheritdoc} */
    public function getLastMessage(): ?Message
    {
        $messages = $this->getMessages();

        if ($messages === []) {
            return null;
        }

        return $messages[array_key_last($messages)];
    }

    /** {@inheritdoc} */
    public function count(): int
    {
        return count($this->loadMessages());
    }

    /** {@inheritdoc} */
    public function flush(): void
    {
        file_put_contents($this->filePath, '[]', LOCK_EX);
    }

    /** {@inheritdoc} */
    public function slice(int $offset, ?int $length = null): array
    {
        $messages = $this->getMessages();

        return array_slice($messages, $offset, $length);
    }

    /**
     * Loads the raw message data from the JSON file.
     *
     * @return array<int, array<string, mixed>> Raw message arrays.
     */
    private function loadMessages(): array
    {
        $content = file_get_contents($this->filePath);

        if ($content === false || $content === '') {
            return [];
        }

        /** @var array<int, array<string, mixed>>|null $decoded */
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Saves serialized messages to the JSON file with exclusive locking.
     *
     * @param array<int, array<string, mixed>> $messages Serialized message arrays.
     */
    private function saveMessages(array $messages): void
    {
        $json = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        file_put_contents($this->filePath, $json, LOCK_EX);
    }

    /**
     * Serializes a Message into a JSON-safe array.
     *
     * @param Message $message The message to serialize.
     * @return array<string, mixed> The serializable representation.
     */
    private function serializeMessage(Message $message): array
    {
        return [
            'role' => $message->getRole()->value,
            'content' => is_string($message->getContent())
                ? $message->getContent()
                : $message->getText(),
        ];
    }

    /**
     * Deserializes a raw array back into a Message object.
     *
     * Supports system, user, and assistant roles. Unknown roles
     * default to UserMessage for resilience.
     *
     * @param array<string, mixed> $data The raw message data.
     * @return Message The deserialized message object.
     */
    private function deserializeMessage(array $data): Message
    {
        $role = MessageRole::tryFrom((string) ($data['role'] ?? 'user'));
        $content = (string) ($data['content'] ?? '');

        return match ($role) {
            MessageRole::System => new SystemMessage($content),
            MessageRole::Assistant => new AssistantMessage($content),
            default => new UserMessage($content),
        };
    }
}
