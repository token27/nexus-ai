<?php

declare(strict_types=1);

namespace Token27\NexusAI\Enum;

/**
 * Type of content in a multi-modal content block.
 *
 * Identifies the kind of data carried by a ContentBlock within a message.
 * Inspired by Neuron AI's ContentBlockType enum.
 *
 * @see \Token27\NexusAI\ValueObject\ContentBlock
 */
enum ContentType: string
{
    /** Plain text content. */
    case Text = 'text';

    /** Image content (base64, URL, or file path). */
    case Image = 'image';

    /** Audio content (base64 or URL). */
    case Audio = 'audio';

    /** Video content (URL). */
    case Video = 'video';

    /** Generic file content (PDF, etc). */
    case File = 'file';

    /**
     * Model "thinking" block (Claude thinking, OpenAI reasoning).
     * Contains the model's internal reasoning process.
     */
    case Reasoning = 'reasoning';
}
