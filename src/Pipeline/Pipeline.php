<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pipeline;

use Token27\NexusAI\Contract\MiddlewareInterface;

/**
 * Engine that executes a chain of middlewares and a final handler.
 *
 * This is the heart of NexusAI's cross-cutting concern system. Uses
 * array_reduce with array inversion to compose the onion model:
 *
 * For middlewares [A, B, C] and handler H:
 *   Execution order: A → B → C → H → C → B → A
 *
 * The Pipeline itself is immutable: pipe() returns a new Pipeline instance.
 *
 * @see \Token27\NexusAI\Contract\MiddlewareInterface
 * @see \Token27\NexusAI\Pipeline\Context
 */
final class Pipeline
{
    /**
     * @param array<MiddlewareInterface> $middleware Stack of registered middlewares.
     */
    public function __construct(
        private readonly array $middleware = [],
    ) {
    }

    /**
     * Adds a middleware to the pipeline. Returns a NEW Pipeline instance (immutable).
     *
     * @param MiddlewareInterface $middleware The middleware to add.
     * @return self A new Pipeline with the middleware appended.
     */
    public function pipe(MiddlewareInterface $middleware): self
    {
        return new self([...$this->middleware, $middleware]);
    }

    /**
     * Executes the full pipeline: all middlewares wrapping the final handler.
     *
     * Uses array_reduce with array_reverse to compose the onion:
     *
     * 1. Reverse the middleware array: [A, B, C] → [C, B, A]
     * 2. Reduce from inner to outer:
     *    - Start with $handler (the core: driver call)
     *    - Wrap C around handler: fn($ctx) => C->process($ctx, $handler)
     *    - Wrap B around that:    fn($ctx) => B->process($ctx, fn => C->process(...))
     *    - Wrap A around that:    fn($ctx) => A->process($ctx, fn => B->process(...))
     * 3. Execute the outermost callable with $context
     *
     * @param Context $context The initial context with the request.
     * @param callable(Context): Context $handler The final handler (typically the driver call).
     * @return Context The final context with response, usage, cost, etc.
     */
    public function send(Context $context, callable $handler): Context
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (callable $next, MiddlewareInterface $mw): callable =>
                fn (Context $ctx): Context => $mw->process($ctx, $next),
            $handler,
        );

        return $pipeline($context);
    }
}
