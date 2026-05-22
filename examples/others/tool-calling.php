<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Tool\Tool;
use Token27\NexusAI\Tool\ToolProperty;
use Token27\NexusAI\Tool\PropertyType;
use Token27\NexusAI\Enum\ToolChoice;

/**
 * Example 03: Tool Calling
 * 
 * This example demonstrates how to provide custom tools to the LLM.
 * The library features an automatic "maxSteps" loop that makes multiple 
 * round-trips to the provider automatically until the LLM replies with 
 * a regular text response or the maximum steps are exhausted.
 */

// 1. Configure provider + HTTP stack.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

// 2. Create custom tools
// Nexus AI allows defining tools statically using a factory pattern.
$weatherTool = Tool::make('get_weather', 'Gets the current weather for a specific location.')
    ->addProperty(
        new ToolProperty(
            name: 'location',
            type: PropertyType::String,
            description: 'The city and state, e.g. "San Francisco, CA"',
            required: true
        )
    )
    ->addProperty(
        new ToolProperty(
            name: 'unit',
            type: PropertyType::String,
            description: 'The temperature unit (celsius or fahrenheit)',
            required: false,
            enum: ['celsius', 'fahrenheit'],
            default: 'celsius'
        )
    )
    ->setCallable(function (array $args) {
        // Implementation of the tool logic.
        $location = $args['location'] ?? 'Unknown';
        $unit = $args['unit'] ?? 'celsius';

        // Let's pretend to call a weather API here
        echo "[DEBUG] Tool 'get_weather' was called for {$location} ({$unit})\n";

        // The return value of the callable is converted to a string and sent back to the LLM
        return json_encode([
            'temperature' => random_int(10, 30),
            'unit' => $unit,
            'condition' => 'partly cloudy',
            'location' => $location
        ]);
    });

echo "Asking the AI a question that requires tools...\n\n";

try {
    // 3. Make the request with tools
    $response = NexusAI::using('openai', 'gpt-4o')
        ->withSystemPrompt('You are a helpful weather assistant.')
        ->withPrompt('What is the weather like in Tokyo and London today?')
        // Provide the array of tools to the request
        ->withTools([$weatherTool])
        // Optional: you can force it to use a tool, or use 'auto'
        ->withToolChoice(ToolChoice::Auto)
        // VERY IMPORTANT: Allow multiple round-trips for the tool calls
        // Without this, the library will just stop after the first tool call
        ->withMaxSteps(5)
        ->asText();

    echo "========= FINAL RESPONSE =========\n";
    echo $response->text . "\n";
    echo "==================================\n\n";

    echo "Total tool calls made: " . count($response->toolCalls) . "\n";

    // 4. Inspect steps and internal iterations
    echo "\nBehind the scenes round-trips (Internal Steps):\n";
    foreach ($response->steps as $stepIndex => $stepData) {
        $stepNum = $stepIndex + 1;
        echo "Step {$stepNum} finished because: " . $stepData->finishReason->value . "\n";
    }

} catch (\Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}



