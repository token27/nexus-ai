<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * Error during streaming (SSE parse error, connection drop, etc).
 *
 * @see \Token27\NexusAI\Stream\Adapter\SSEAdapter
 * @see \Token27\NexusAI\Contract\StreamChunkInterface
 */
class StreamException extends NexusException
{
}
