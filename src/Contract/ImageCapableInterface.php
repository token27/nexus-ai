<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

use Token27\NexusAI\Request\ImageRequest;
use Token27\NexusAI\Response\ImageResponse;

/**
 * Optional interface for drivers that support image generation.
 *
 * Separated from the main DriverInterface so that providers without
 * image capabilities (e.g., Ollama, DeepSeek) don't need to declare
 * unsupported methods. Use `$driver instanceof ImageCapableInterface`
 * or `$driver->supports(DriverInterface::CAPABILITY_IMAGES)` to check.
 *
 * @see \Token27\NexusAI\Contract\DriverInterface
 * @see \Token27\NexusAI\Request\ImageRequest
 * @see \Token27\NexusAI\Response\ImageResponse
 */
interface ImageCapableInterface
{
    /**
     * Generates image(s) from a text prompt.
     *
     * @param ImageRequest $request The image generation request with prompt and parameters.
     * @return ImageResponse The generated image(s) response.
     */
    public function image(ImageRequest $request): ImageResponse;
}
