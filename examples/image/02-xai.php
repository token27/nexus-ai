<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Image Pricing Example: xAI ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('XAI_API_KEY');
    // Default model follows current xAI docs naming.
    $model = envOrNull('XAI_IMAGE_MODEL') ?? 'grok-imagine-image';

    // 2) Configure Nexus with xAI credentials and pricing middleware.
    bootNexus([
        'xai' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Generate image with xAI-specific options.
    // Here we explicitly request base64 response so we can save locally.
    $response = NexusAI::using('xai', $model)
        ->withPrompt('An editorial style portrait of a robot barista in a modern cafe.')
        ->withResolution('1k')
        ->withOption('response_format', 'b64_json')
        ->asImage();

    // 4) Print image summary + usage + pricing details.
    printImageResponseSummary($response);

    // 5) Save first image if provider returned base64 payload.
    $outputPath = __DIR__ . '/output-xai.png';
    if (saveFirstImageIfBase64($response, $outputPath)) {
        echo 'Saved first image to: ' . $outputPath . PHP_EOL;
    }
} catch (Throwable $e) {
    // Keep output concise and focused.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
