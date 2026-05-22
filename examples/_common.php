<?php

declare(strict_types=1);

// -------------------------------------------------------------------------
// Shared bootstrap/helpers for all example scripts (text + image).
//
// Goal:
// - Keep every example tiny and focused on the provider flow.
// - Centralize repetitive setup (autoload, .env, HTTP client, pricing).
// - Provide consistent output formatting for usage + pricing.
// -------------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Response\ImageResponse;
use Token27\NexusAI\Response\TextResponse;

// Try to load environment variables as soon as this file is included.
// This makes `php examples/text/04-deepseek.php` work without requiring
// the user to export vars manually in the terminal.
loadDotEnvIfPresent();

/**
 * Loads the first `.env` file found from the candidate list.
 *
 * Search order:
 * 1) project root:      nexus-ai/.env
 * 2) examples folder:   nexus-ai/examples/.env
 *
 * Behavior:
 * - Existing process env vars are NOT overwritten.
 * - Empty lines and comments are ignored.
 * - Simple KEY=VALUE parsing (good enough for examples).
 */
function loadDotEnvIfPresent(): void
{
    // Avoid parsing multiple times if many examples include this file.
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $candidates = [
        __DIR__ . '/../.env',
        __DIR__ . '/.env',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }

        // Read as lines to parse .env syntax manually.
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Ignore comments and blank lines.
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Split only once: VALUE can contain "=" after the first one.
            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            // Remove optional surrounding quotes.
            $value = trim($value, " \t\n\r\0\x0B\"'");

            // Keep precedence: if var already exists, we respect it.
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        $loaded = true;

        return;
    }
}

/**
 * Returns env var or null when missing/empty.
 */
function envOrNull(string $key): ?string
{
    $value = getenv($key);

    if ($value === false || trim($value) === '') {
        return null;
    }

    return $value;
}

/**
 * Returns env var or throws a descriptive error.
 *
 * Use this for required credentials such as API keys.
 */
function requireEnv(string $key): string
{
    $value = envOrNull($key);
    if ($value === null) {
        throw new RuntimeException("Missing environment variable: {$key}");
    }

    return $value;
}

/**
 * Boots NexusAI with:
 * - HTTP client/factories
 * - provider configuration
 * - pricing middleware enabled globally
 *
 * This keeps each example focused on request flow instead of plumbing.
 *
 * @param array<string, array<string, mixed>> $providers
 */
function bootNexus(
    array $providers,
    ?PricingEngineInterface $engine = null,
    ?float $budgetLimit = null,
): void
{
    $factory = new HttpFactory();

    // Reset static facade state to avoid cross-example contamination
    // when running many examples in the same process.
    NexusAI::reset();
    NexusAI::setHttpClient(new Client([
        'timeout' => 60,
        // For local Windows dev environments where CA bundle may be missing.
        'verify' => false,
    ]));
    NexusAI::setFactories($factory, $factory);
    NexusAI::configure($providers);
    NexusAI::withPricing(
        $engine ?? PricingEngine::withRegistry(PricingRegistry::createDefault()),
        $budgetLimit,
    );
}

/**
 * Prints a compact but complete summary for a TextResponse.
 */
function printTextResponseSummary(TextResponse $response): void
{
    // Keep preview short so terminal output stays readable.
    $preview = preg_replace('/\s+/', ' ', trim($response->text)) ?? '';
    if (strlen($preview) > 220) {
        $preview = substr($preview, 0, 217) . '...';
    }

    echo 'Text preview: ' . $preview . PHP_EOL;
    echo 'Usage:' . PHP_EOL;
    echo '  - text input tokens: ' . $response->usage->textInputTokens . PHP_EOL;
    echo '  - text output tokens: ' . $response->usage->textOutputTokens . PHP_EOL;
    echo '  - total tokens: ' . $response->usage->totalTokens() . PHP_EOL;

    // Pricing result is attached only if pricing middleware is active.
    if ($response->pricingResult === null) {
        echo 'Pricing: not available (did you call NexusAI::withPricing?).' . PHP_EOL;

        return;
    }

    echo 'Pricing:' . PHP_EOL;
    echo '  - total USD: ' . $response->pricingResult->totalCostUsd() . PHP_EOL;
    echo '  - formatted: ' . $response->pricingResult->format() . PHP_EOL;
    echo '  - unknown model: ' . ($response->pricingResult->isUnknownModel() ? 'yes' : 'no') . PHP_EOL;
}

/**
 * Prints a compact but complete summary for an ImageResponse.
 */
function printImageResponseSummary(ImageResponse $response): void
{
    $count = count($response->images);
    $first = $response->images[0] ?? null;

    echo 'Images: ' . $count . PHP_EOL;
    if ($first !== null) {
        $base64Len = strlen($first->base64 ?? '');
        if ($base64Len > 0) {
            echo 'First image base64 length: ' . $base64Len . PHP_EOL;
        } elseif ($first->url !== null) {
            echo 'First image URL: ' . $first->url . PHP_EOL;
        }
    }

    echo 'Usage:' . PHP_EOL;
    echo '  - text input tokens: ' . $response->usage->textInputTokens . PHP_EOL;
    echo '  - image output tokens: ' . $response->usage->imageOutputTokens . PHP_EOL;
    echo '  - total tokens: ' . $response->usage->totalTokens() . PHP_EOL;

    // Pricing result is attached only if pricing middleware is active.
    if ($response->pricingResult === null) {
        echo 'Pricing: not available (did you call NexusAI::withPricing?).' . PHP_EOL;

        return;
    }

    echo 'Pricing:' . PHP_EOL;
    echo '  - total USD: ' . $response->pricingResult->totalCostUsd() . PHP_EOL;
    echo '  - image output USD: ' . $response->pricingResult->imageOutputCostUsd() . PHP_EOL;
    echo '  - formatted: ' . $response->pricingResult->format() . PHP_EOL;
    echo '  - unknown model: ' . ($response->pricingResult->isUnknownModel() ? 'yes' : 'no') . PHP_EOL;
}

/**
 * Saves first generated image to disk when image data is base64.
 *
 * Returns:
 * - true  -> image saved
 * - false -> no base64 payload or invalid data
 */
function saveFirstImageIfBase64(ImageResponse $response, string $path): bool
{
    $first = $response->images[0] ?? null;
    if ($first === null || ($first->base64 ?? '') === '') {
        return false;
    }

    $decoded = base64_decode($first->base64 ?? '', true);
    if ($decoded === false) {
        return false;
    }

    file_put_contents($path, $decoded);

    return true;
}
