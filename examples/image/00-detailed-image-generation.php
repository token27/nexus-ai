<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example: Detailed Image Generation
 *
 * This walkthrough demonstrates multiple image scenarios in one file:
 * 1) low quality PNG
 * 2) high quality JPEG with compression
 * 3) transparent background
 *
 * It also shows usage and pricing after each request.
 */

echo "=== Detailed Image Generation ===" . PHP_EOL . PHP_EOL;

try {
    // 1) Read provider credentials and model from environment variables.
    $apiKey = requireEnv('OPENAI_API_KEY');
    $model = envOrNull('OPENAI_IMAGE_MODEL') ?? 'gpt-image-1';

    // 2) Boot Nexus with OpenAI credentials + pricing middleware.
    bootNexus([
        'openai' => [
            'api_key' => $apiKey,
        ],
    ]);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "--- 1) Low quality PNG ---" . PHP_EOL;

try {
    $response = NexusAI::using('openai', $model)
        ->withPrompt('A serene Japanese zen garden at dawn, cherry blossoms')
        ->withSize('1024x1024')
        ->withQuality('low')
        ->withOutputFormat('png')
        ->asImage();

    printImageResponseSummary($response);

    // Save first image if base64 was returned.
    $path = __DIR__ . '/output-detailed-01.png';
    if (saveFirstImageIfBase64($response, $path)) {
        echo "Saved: {$path}" . PHP_EOL;
    }

    echo PHP_EOL;
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL . PHP_EOL;
}

echo "--- 2) High quality JPEG with compression ---" . PHP_EOL;

try {
    $response = NexusAI::using('openai', $model)
        ->withPrompt('A futuristic city skyline at night, neon reflections')
        ->withSize('1536x1024')
        ->withQuality('high')
        ->withOutputFormat('jpeg')
        ->withOutputCompression(85)
        ->asImage();

    printImageResponseSummary($response);

    $path = __DIR__ . '/output-detailed-02.jpg';
    if (saveFirstImageIfBase64($response, $path)) {
        echo "Saved: {$path}" . PHP_EOL;
    }

    echo PHP_EOL;
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL . PHP_EOL;
}

echo "--- 3) Transparent background ---" . PHP_EOL;

try {
    $response = NexusAI::using('openai', $model)
        ->withPrompt('A minimalist logo, isolated')
        ->withSize('1024x1024')
        ->withOutputFormat('png')
        ->withBackground('transparent')
        ->asImage();

    printImageResponseSummary($response);

    $path = __DIR__ . '/output-detailed-03.png';
    if (saveFirstImageIfBase64($response, $path)) {
        echo "Saved: {$path}" . PHP_EOL;
    }

    echo PHP_EOL;
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL . PHP_EOL;
}
