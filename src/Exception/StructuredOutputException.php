<?php

declare(strict_types=1);

namespace Token27\NexusAI\Exception;

/**
 * The LLM returned output that could not be parsed to the expected schema.
 *
 * Contains the raw text, expected schema, and validation violations
 * for debugging and retry logic.
 *
 * @see \Token27\NexusAI\StructuredOutput\Deserializer
 * @see \Token27\NexusAI\StructuredOutput\Validation\Validator
 */
class StructuredOutputException extends NexusException
{
    /**
     * @param string $message Human-readable error message.
     * @param string $responseText Raw text returned by the LLM.
     * @param array<string, mixed> $schema Expected JSON Schema.
     * @param array<string> $violations List of validation violations (if any).
     */
    public function __construct(
        string $message,
        public readonly string $responseText,
        public readonly array $schema,
        public readonly array $violations = [],
    ) {
        parent::__construct($message);
    }
}
