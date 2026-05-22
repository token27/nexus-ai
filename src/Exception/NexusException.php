<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

use RuntimeException;

/**
 * Base exception for ALL NexusAI errors.
 *
 * Consumers can catch this single type to handle any error from the engine:
 *   catch (NexusException $e) { ... }
 *
 * @see \Token27\NexusAI\Exception\DriverException
 * @see \Token27\NexusAI\Exception\CostLimitException
 */
class NexusException extends RuntimeException
{
}
