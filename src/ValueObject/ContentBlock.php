<?php

declare(strict_types=1);

namespace Token27\NexusAI\ValueObject;

use Token27\NexusAI\Enum\ContentType;

/**
 * Multi-modal content block within a message.
 *
 * A single VO replaces Neuron AI's 5 subclasses (TextContent, ImageContent, etc.)
 * and Prism's separate part classes. The ContentType enum discriminates the type.
 *
 * @see \Token27\NexusAI\Enum\ContentType
 * @see \Token27\NexusAI\Message\UserMessage
 */
readonly class ContentBlock
{
    /**
     * @param ContentType $type Content type (Text, Image, Audio, etc).
     * @param string $content Data: plain text, base64, URL, or file path depending on sourceType.
     * @param string|null $sourceType Source format: 'base64', 'url', 'file', or null (for text).
     * @param string|null $mediaType MIME type (e.g., 'image/png', 'audio/mp3'). Required for base64.
     * @param array<string, mixed> $metadata Free key-value data (e.g., ['width' => 1024, 'height' => 768]).
     */
    public function __construct(
        public ContentType $type,
        public string $content,
        public ?string $sourceType = null,
        public ?string $mediaType = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Creates a text content block.
     *
     * @param string $text The text content.
     */
    public static function text(string $text): self
    {
        return new self(
            type: ContentType::Text,
            content: $text,
        );
    }

    /**
     * Creates an image content block from a URL.
     *
     * @param string $url The image URL.
     */
    public static function imageUrl(string $url): self
    {
        return new self(
            type: ContentType::Image,
            content: $url,
            sourceType: 'url',
        );
    }

    /**
     * Creates an image content block from base64-encoded data.
     *
     * @param string $data Base64-encoded image data.
     * @param string $mediaType MIME type (e.g., 'image/png', 'image/jpeg').
     */
    public static function imageBase64(string $data, string $mediaType): self
    {
        return new self(
            type: ContentType::Image,
            content: $data,
            sourceType: 'base64',
            mediaType: $mediaType,
        );
    }

    /**
     * Creates an audio content block from a URL.
     *
     * @param string $url The audio URL.
     */
    public static function audioUrl(string $url): self
    {
        return new self(
            type: ContentType::Audio,
            content: $url,
            sourceType: 'url',
        );
    }

    /**
     * Creates a file content block.
     *
     * @param string $path File path or URL.
     * @param string $mediaType MIME type (e.g., 'application/pdf').
     */
    public static function file(string $path, string $mediaType): self
    {
        return new self(
            type: ContentType::File,
            content: $path,
            sourceType: 'file',
            mediaType: $mediaType,
        );
    }
}
