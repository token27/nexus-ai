<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pipeline\Middleware\CacheMiddleware;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;

final class CacheMiddlewareTest extends TestCase
{
    private function makeContext(string $prompt = 'Hello'): Context
    {
        return new Context(new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage($prompt)],
        ));
    }

    private function makeInMemoryCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->store[$key] ?? $default;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->store[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->store = [];
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                $r = [];
                foreach ($keys as $k) {
                    $r[$k] = $this->get($k, $default);
                } return $r;
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                foreach ($values as $k => $v) {
                    $this->set($k, $v, $ttl);
                } return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                foreach ($keys as $k) {
                    $this->delete($k);
                } return true;
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }
        };
    }

    public function testCacheHitReturnsCachedResponse(): void
    {
        $cache = $this->makeInMemoryCache();
        $mw = new CacheMiddleware($cache);
        $callCount = 0;

        $handler = function (Context $ctx) use (&$callCount): Context {
            $callCount++;
            return $ctx->withResponse(new TextResponse(text: 'Fresh', finishReason: FinishReason::Stop));
        };

        // First call — cache miss
        $mw->process($this->makeContext('Same prompt'), $handler);
        $this->assertSame(1, $callCount);

        // Second call with same prompt — cache hit
        $result2 = $mw->process($this->makeContext('Same prompt'), $handler);
        $this->assertSame(1, $callCount);
        $this->assertSame('Fresh', $result2->getResponse()->text);
    }

    public function testDifferentPromptsGetDifferentCache(): void
    {
        $cache = $this->makeInMemoryCache();
        $mw = new CacheMiddleware($cache);
        $callCount = 0;

        $handler = function (Context $ctx) use (&$callCount): Context {
            $callCount++;
            return $ctx->withResponse(new TextResponse(
                text: "Response {$callCount}",
                finishReason: FinishReason::Stop,
            ));
        };

        $mw->process($this->makeContext('Prompt A'), $handler);
        $mw->process($this->makeContext('Prompt B'), $handler);

        $this->assertSame(2, $callCount);
    }

    public function testCachedDataIsPlainArrayNotFullResponse(): void
    {
        $cache = $this->makeInMemoryCache();
        $mw = new CacheMiddleware($cache);

        $handler = function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(
                text: 'Cached text',
                finishReason: FinishReason::Stop,
                usage: new Usage(100, 50),
            ));
        };

        // Execute to populate cache
        $mw->process($this->makeContext('Cache key'), $handler);

        // Retrieve raw cache entry
        $raw = $cache->get($this->getCacheKeyFromMiddleware($mw, 'Cache key'));
        $this->assertIsArray($raw, 'Cached data should be a plain array');
        $this->assertArrayHasKey('class', $raw);
        $this->assertArrayHasKey('text', $raw);
        $this->assertArrayHasKey('finishReason', $raw);
        $this->assertArrayHasKey('usage', $raw);
        $this->assertSame('Cached text', $raw['text']);

        // Verify usage is stored as plain values (not objects)
        $this->assertSame(100, $raw['usage']['textInputTokens']);
        $this->assertSame(50, $raw['usage']['textOutputTokens']);
    }

    public function testCacheHitReconstructsTextResponse(): void
    {
        $cache = $this->makeInMemoryCache();
        $mw = new CacheMiddleware($cache);

        $handler = function (Context $ctx): Context {
            return $ctx->withResponse(new TextResponse(
                text: 'Original',
                finishReason: FinishReason::Stop,
                usage: new Usage(200, 100),
            ));
        };

        // Populate cache
        $mw->process($this->makeContext('Reconstruct test'), $handler);

        // Cache hit
        $result = $mw->process($this->makeContext('Reconstruct test'), $handler);
        $response = $result->getResponse();

        $this->assertNotNull($response);
        $this->assertSame('Original', $response->text);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertSame(200, $response->usage->textInputTokens);
    }

    /**
     * Helper to extract the cache key generated by the middleware.
     * Replicates the key generation logic to inspect cached data.
     */
    private function getCacheKeyFromMiddleware(CacheMiddleware $mw, string $prompt): string
    {
        $ref = new \ReflectionClass($mw);
        $method = $ref->getMethod('generateKey');
        $request = new TextRequest(
            provider: 'openai',
            model: 'gpt-4o',
            messages: [new UserMessage($prompt)],
        );

        return $method->invoke($mw, $request);
    }
}
