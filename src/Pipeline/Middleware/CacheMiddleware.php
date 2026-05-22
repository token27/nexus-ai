<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline\Middleware;

use Psr\SimpleCache\CacheInterface;
use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Contract\ResponseInterface;
use Token27\NexusAI\Enum\FinishReason;
use Token27\NexusAI\Pipeline\Context;
use Token27\NexusAI\Pricing\ValueObject\Usage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\Response\TextResponse;
use Token27\NexusAI\ValueObject\Meta;

/**
 * Caches identical responses using PSR-16 SimpleCache.
 *
 * On cache hit, performs a short-circuit: returns the cached response
 * immediately WITHOUT calling $next(), avoiding redundant API calls.
 *
 * Cache key is deterministic: md5 hash of provider + model + messages + options.
 *
 * Does NOT cache when:
 * - The request has streaming enabled (results are progressive)
 * - The request has tools (results are non-deterministic)
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 */
final class CacheMiddleware implements MiddlewareInterface
{
    /**
     * @param CacheInterface $cache PSR-16 SimpleCache implementation.
     * @param int $ttl Time-to-live in seconds for cached entries.
     * @param string $prefix Prefix for all cache keys to avoid collisions.
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
        private readonly string $prefix = 'nexus:',
    ) {
    }

    /**
     * Checks cache before calling the pipeline, stores response on cache miss.
     *
     * Short-circuits on cache hit: does NOT call $next().
     *
     * @param Context $context The current pipeline context.
     * @param callable(Context): Context $next The next middleware or final handler.
     * @return Context The context with response (from cache or from pipeline).
     */
    public function process(Context $context, callable $next): Context
    {
        $request = $context->getRequest();

        // Do NOT cache streaming or tool-calling requests
        if ($this->shouldSkipCache($request)) {
            return $next($context);
        }

        $key = $this->generateKey($request);

        // Check cache — short-circuit on hit
        $cached = $this->cache->get($key);
        if ($cached !== null && is_array($cached)) {
            return $context->withResponse($this->reconstructFromCache($cached));
        }

        // Cache miss — execute pipeline
        $context = $next($context);

        // Store response in cache as serializable data
        $response = $context->getResponse();
        if ($response !== null) {
            $this->cache->set($key, $this->serializeForCache($response), $this->ttl);
        }

        return $context;
    }

    /**
     * Generates a deterministic cache key from the request parameters.
     *
     * @param \Token27\NexusAI\Contract\RequestInterface $request The request.
     * @return string The cache key.
     */
    private function generateKey(\Token27\NexusAI\Contract\RequestInterface $request): string
    {
        $data = [
            $request->getProvider(),
            $request->getModel(),
            $request->getMessages(),
            $request->getOptions(),
        ];

        return $this->prefix . md5(serialize($data));
    }

    /**
     * Determines whether caching should be skipped for this request.
     *
     * Streaming and tool-calling requests are non-deterministic or progressive,
     * so caching their results would be incorrect.
     *
     * @param \Token27\NexusAI\Contract\RequestInterface $request The request.
     * @return bool True if cache should be skipped.
     */
    private function shouldSkipCache(\Token27\NexusAI\Contract\RequestInterface $request): bool
    {
        // Skip if request has tools (TextRequest with non-empty tools array)
        if ($request instanceof TextRequest && !empty($request->tools)) {
            return true;
        }

        // Skip if streaming is enabled via options
        $options = $request->getOptions();
        if (isset($options['stream']) && $options['stream'] === true) {
            return true;
        }

        return false;
    }

    /**
     * Extracts serializable data from a response for cache storage.
     *
     * Avoids storing full Response objects which may contain closures or
     * resources that would fail serialization.
     *
     * @param ResponseInterface $response The response to serialize.
     * @return array<string, mixed> Cache-safe data.
     */
    private function serializeForCache(ResponseInterface $response): array
    {
        return [
            'class' => get_class($response),
            'text' => method_exists($response, 'getText') ? $response->getText() : ($response->text ?? null),
            'finishReason' => $response->getFinishReason()->value,
            'usage' => [
                'textInputTokens' => $response->getUsage()->textInputTokens(),
                'textOutputTokens' => $response->getUsage()->textOutputTokens(),
            ],
            'raw' => $response->getRaw(),
        ];
    }

    /**
     * Reconstructs a ResponseInterface from cached data.
     *
     * Currently reconstructs TextResponse. Extend this method when
     * additional response types need cache support.
     *
     * @param array<string, mixed> $data Cached data from serializeForCache().
     * @return ResponseInterface The reconstructed response.
     */
    private function reconstructFromCache(array $data): ResponseInterface
    {
        $usage = new Usage(
            textInputTokens: (int) ($data['usage']['textInputTokens'] ?? 0),
            textOutputTokens: (int) ($data['usage']['textOutputTokens'] ?? 0),
        );

        $finishReason = FinishReason::from($data['finishReason']);

        return new TextResponse(
            text: (string) ($data['text'] ?? ''),
            finishReason: $finishReason,
            usage: $usage,
            meta: new Meta(),
            raw: $data['raw'] ?? null,
        );
    }
}
