<?php

declare(strict_types=1);

namespace Token27\NexusAI\Contract;

/**
 * Contract for JSON Schema definitions used in structured output.
 *
 * Implementations provide a JSON Schema that the LLM must conform to
 * when generating structured output. The schema is sent to the provider
 * API in the appropriate format (response_format, tool parameters, etc.).
 *
 * Inspired by Prism's Schema interface with toArray()/name()/description().
 *
 * @see \Token27\NexusAI\Enum\StructuredMode
 */
interface SchemaInterface
{
    /**
     * Returns the complete JSON Schema as a PHP array, ready for json_encode().
     *
     * @return array<string, mixed> The JSON Schema definition.
     */
    public function toArray(): array;

    /**
     * Returns the schema name, used as identifier in the API.
     *
     * For example, in OpenAI: response_format.json_schema.name
     *
     * @return string The schema name.
     */
    public function getName(): string;

    /**
     * Returns an optional description for the LLM to understand the schema purpose.
     *
     * @return string|null The schema description, or null if not provided.
     */
    public function getDescription(): ?string;
}
