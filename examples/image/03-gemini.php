<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Image Pricing Example: Gemini ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('GEMINI_API_KEY');

    // 2) Use an image-capable Gemini model.
    // You can override this in .env with GEMINI_IMAGE_MODEL.
    $model = envOrNull('GEMINI_IMAGE_MODEL') ?? 'gemini-3.1-flash-image-preview';

    // 3) Configure Nexus with Gemini credentials and pricing middleware.
    bootNexus([
        'gemini' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 4) Execute image generation request.
    // Gemini image models return image bytes in the response parts.
    $response = NexusAI::using('gemini', $model)
        ->withPrompt('A vibrant watercolor illustration of a mountain village at sunrise.')
        ->withSize('1024x1024')
        ->asImage();

    // 5) Print image summary + usage + pricing details.
    printImageResponseSummary($response);

    // 6) Save first image if provider returned base64 payload.
    $outputPath = __DIR__ . '/output-gemini.png';
    if (saveFirstImageIfBase64($response, $outputPath)) {
        echo 'Saved first image to: ' . $outputPath . PHP_EOL;
    }
} catch (Throwable $e) {
    // Keep examples easy to read in terminal.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
