<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit;

use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Contract\ImageCapableInterface;
use Token27\NexusAI\Driver\DriverRegistry;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\PendingRequest;
use Token27\NexusAI\Pipeline\Middleware\CostTrackingMiddleware;
use Token27\NexusAI\Pipeline\Pipeline;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\EmbeddingRequest;
use Token27\NexusAI\Request\ImageRequest;
use Token27\NexusAI\Request\StructuredRequest;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\EmbeddingResponse;
use Token27\NexusAI\Response\ImageResponse;
use Token27\NexusAI\Response\StructuredResponse;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\Stream\TextChunk;
use Token27\NexusAI\ValueObject\GeneratedImage;

final class PendingRequestPricingResultTest extends TestCase
{
    public function testAsTextIncludesPricingResultWhenPricingMiddlewareIsActive(): void
    {
        $request = $this->makePendingRequest(model: 'gpt-4o', withPricing: true);

        $response = $request
            ->withPrompt('Say hello')
            ->asText();

        $this->assertNotNull($response->pricingResult);
        $this->assertGreaterThan(0.0, $response->pricingResult->totalCostUsd());
    }

    public function testAsImageIncludesPricingResultWhenPricingMiddlewareIsActive(): void
    {
        $request = $this->makePendingRequest(model: 'gpt-image-1', withPricing: true);

        $response = $request
            ->withPrompt('A red circle')
            ->withOutputFormat('png')
            ->asImage();

        $this->assertNotNull($response->pricingResult);
        $this->assertGreaterThan(0.0, $response->pricingResult->totalCostUsd());
    }

    public function testAsTextHasNullPricingResultWithoutPricingMiddleware(): void
    {
        $request = $this->makePendingRequest(model: 'gpt-4o', withPricing: false);

        $response = $request
            ->withPrompt('Say hello')
            ->asText();

        $this->assertNull($response->pricingResult);
    }

    private function makePendingRequest(string $model, bool $withPricing): PendingRequest
    {
        $driver = new class () implements DriverInterface, ImageCapableInterface {
            public function text(TextRequest $request): TextResponse
            {
                return new TextResponse(
                    text: 'ok',
                    finishReason: FinishReason::Stop,
                    usage: new Usage(textInputTokens: 100, textOutputTokens: 20),
                );
            }

            public function structured(StructuredRequest $request): StructuredResponse
            {
                throw new \LogicException('Not used in this test.');
            }

            public function embeddings(EmbeddingRequest $request): EmbeddingResponse
            {
                throw new \LogicException('Not used in this test.');
            }

            public function stream(TextRequest $request): Generator
            {
                if (false) {
                    yield new TextChunk(text: '');
                }
            }

            public function supports(string $capability): bool
            {
                return true;
            }

            public function image(ImageRequest $request): ImageResponse
            {
                return new ImageResponse(
                    images: [new GeneratedImage(base64: 'aGVsbG8=')],
                    usage: new Usage(textInputTokens: 30, imageOutputTokens: 1_250),
                    finishReason: FinishReason::Stop,
                );
            }
        };

        $registry = new DriverRegistry();
        $registry->register(
            'fake',
            static fn (
                array $_config,
                ClientInterface $_httpClient,
                RequestFactoryInterface $_requestFactory,
                StreamFactoryInterface $_streamFactory,
            ): DriverInterface => $driver,
        );
        $registry->setHttpDependencies(
            $this->createMock(ClientInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
        );

        $pipeline = new Pipeline();
        if ($withPricing) {
            $engine = PricingEngine::withRegistry(PricingRegistry::createDefault());
            $pipeline = $pipeline->pipe(new CostTrackingMiddleware($engine));
        }

        return new PendingRequest($registry, $pipeline, 'fake', $model);
    }
}
