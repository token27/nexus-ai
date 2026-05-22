<?php

declare(strict_types=1);

namespace Token27\NexusAI\ValueObject;

/**
 * Value object representing a single generated image.
 *
 * Contains either a URL or base64-encoded image data depending on the
 * responseFormat specified in the ImageRequest. DALL-E 3 may also return
 * a revised prompt that differs from the original.
 *
 * @see \Token27\NexusAI\Response\ImageResponse
 * @see \Token27\NexusAI\Request\ImageRequest
 */
final readonly class GeneratedImage
{
    /**
     * @param string|null $url URL of the generated image (when responseFormat is 'url').
     * @param string|null $base64 Base64-encoded image data (when responseFormat is 'b64_json').
     * @param string|null $revisedPrompt Prompt as revised by the model (DALL-E 3 may modify the original prompt).
     */
    public function __construct(
        public ?string $url = null,
        public ?string $base64 = null,
        public ?string $revisedPrompt = null,
    ) {
    }
}
