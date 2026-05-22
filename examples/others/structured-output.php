<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\StructuredOutput\SchemaProperty;
use Token27\NexusAI\StructuredOutput\Validation\Rules\Required;
use Token27\NexusAI\StructuredOutput\Validation\Rules\MaxLength;

/**
 * Example 04: Structured Output
 * 
 * Demonstrates how to force an LLM to reply with a strictly formatted
 * JSON payload, deserialize it automatically into a typed PHP object,
 * and validate the properties.
 */

// 1. Define a DTO (Data Transfer Object) with attributes for Schema generation
class RecipeDTO
{
    #[Required]
    #[MaxLength(100)]
    #[SchemaProperty(description: 'The name of the recipe.', required: true)]
    public string $title;

    #[Required]
    #[SchemaProperty(description: 'Estimated preparation time in minutes.', required: true)]
    public int $prepTime;

    #[SchemaProperty(
        description: 'Difficulty level',
        enum: ['easy', 'medium', 'hard'],
        required: true
    )]
    public string $difficulty;

    /** @var string[] */
    #[SchemaProperty(description: 'List of ingredients.', required: true, type: 'string')]
    public array $ingredients = [];
}

// 2. Configure provider + HTTP stack.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

echo "Generating a structured recipe object...\n\n";

try {
    // 3. Request a Structured Response
    // We use `asStructured` and provide the DTO class name. The library will:
    // a) reflect the constructor/properties to generate a JSON schema
    // b) instruct the LLM to follow that schema using Native Structured Output or Tool Output
    // c) deserialize the JSON back to a RecipeDTO instance
    // d) perform retries automatically if JSON is invalid or missing
    $response = NexusAI::using('openai', 'gpt-4o-mini')
        ->withSystemPrompt('You are an expert chef. Reply using the schema provided.')
        ->withPrompt('Give me a simple recipe for scrambled eggs.')
        ->asStructured(RecipeDTO::class);

    // 4. Access the strongly typed response object
    /** @var RecipeDTO $recipe */
    $recipe = $response->object;

    echo "========= DTO PROPERTIES =========\n";
    echo "Title: " . $recipe->title . "\n";
    echo "Prep Time: " . $recipe->prepTime . " minutes\n";
    echo "Difficulty: " . $recipe->difficulty . "\n";
    echo "\nIngredients:\n";
    foreach ($recipe->ingredients as $ingredient) {
        echo "- " . $ingredient . "\n";
    }
    echo "==================================\n\n";

    echo "Attempts made by library to parse/validate: " . $response->attempts . "\n";

} catch (\Token27\NexusAI\Exception\StructuredOutputException $e) {
    echo "Failed to generate structured valid output:\n";
    echo $e->getMessage() . "\n";
    if (!empty($e->violations)) {
        header('Content-Type: text/plain');
        print_r($e->violations);
    }
} catch (\Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}



