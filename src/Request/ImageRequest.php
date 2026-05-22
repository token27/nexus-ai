<?php

declare(strict_types=1);

namespace Token27\NexusAI\Request;

use Token27\NexusAI\Contract\RequestInterface;
use Token27\NexusAI\Message\Message;

final readonly class ImageRequest implements RequestInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $prompt,
        public ?string $size = null,
        public ?string $quality = null,
        public ?string $outputFormat = null,
        public ?int $outputCompression = null,
        public ?string $background = null,
        public ?string $moderation = null,
        public int $n = 1,
        public ?string $resolution = null,
        public ?string $responseFormat = null,
        public array $options = [],
    ) {
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return [];
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function getTemperature(): ?float
    {
        return null;
    }
}
