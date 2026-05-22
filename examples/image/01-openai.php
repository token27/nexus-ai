<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Image Pricing Example: OpenAI ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('OPENAI_API_KEY');
    // Optional override to try other OpenAI image models.
    $model = envOrNull('OPENAI_IMAGE_MODEL') ?? 'gpt-image-1';

    // 2) Configure Nexus with OpenAI credentials and pricing middleware.
    bootNexus([
        'openai' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Execute image generation request.
    // This sample asks for PNG to make local save straightforward.
    $response = NexusAI::using('openai', $model)
        ->withPrompt('A cinematic photo of a red fox in a snowy forest at sunrise.')
        ->withSize('1024x1024')
        ->withQuality('low')
        ->withOutputFormat('png')
        ->asImage();

    // 4) Print image summary + usage + pricing details.
    printImageResponseSummary($response);

    // 5) Save first image if provider returned base64 payload.
    $outputPath = __DIR__ . '/output-openai.png';
    if (saveFirstImageIfBase64($response, $outputPath)) {
        echo 'Saved first image to: ' . $outputPath . PHP_EOL;
    }
} catch (Throwable $e) {
    // Keep examples easy to read in terminal.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
