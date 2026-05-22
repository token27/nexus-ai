<?php

declare(strict_types=1);

namespace Token27\NexusAI\Response;

use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\ValueObject\GeneratedImage;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Result of an image generation request.
 *
 * Contains one or more GeneratedImage value objects, each holding either
 * a URL or base64-encoded image data. Token usage may be reported by some
 * providers; finishReason is always Stop for image generation.
 *
 * @see \Token27\NexusAI\Contract\ImageCapableInterface::image()
 * @see \Token27\NexusAI\Request\ImageRequest
 * @see \Token27\NexusAI\ValueObject\GeneratedImage
 */
final readonly class ImageResponse implements ResponseInterface
{
    /**
     * @param array<GeneratedImage> $images List of generated images.
     * @param Usage $usage Token usage (some providers report this for images).
     * @param Meta $meta Response metadata (ID, model, rate limits).
     * @param FinishReason $finishReason Always Stop for image generation.
     * @param array<string, mixed>|null $raw Raw JSON response from the provider.
     * @param PricingResultInterface|null $pricingResult Calculated pricing result when pricing middleware is active.
     */
    public function __construct(
        public array $images,
        public Usage $usage = new Usage(),
        public Meta $meta = new Meta(),
        public FinishReason $finishReason = FinishReason::Stop,
        public ?array $raw = null,
        public ?PricingResultInterface $pricingResult = null,
    ) {
    }

    public function withPricingResult(PricingResultInterface $pricingResult): self
    {
        return new self(
            images: $this->images,
            usage: $this->usage,
            meta: $this->meta,
            finishReason: $this->finishReason,
            raw: $this->raw,
            pricingResult: $pricingResult,
        );
    }

    /** {@inheritdoc} */
    public function getUsage(): Usage
    {
        return $this->usage;
    }

    /** {@inheritdoc} */
    public function getMeta(): Meta
    {
        return $this->meta;
    }

    /** {@inheritdoc} */
    public function getFinishReason(): FinishReason
    {
        return $this->finishReason;
    }

    /** {@inheritdoc} */
    public function getRaw(): ?array
    {
        return $this->raw;
    }
}
