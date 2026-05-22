<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example 16: Text-to-Speech (TTS)
 *
 * Demonstrates how to convert text into audio using the asSpeech() terminal method.
 * The driver must implement AudioCapableInterface (e.g., OpenAI with tts-1 / tts-1-hd).
 *
 * Available OpenAI voices: 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'
 *
 * Fluent setters for TTS:
 *   ->withPrompt(string)       - The text to convert to audio (required)
 *   ->withVoice(string)        - Voice identifier (required)
 *   ->withAudioFormat(string)  - 'mp3' (default), 'opus', 'aac', 'flac'
 *   ->withSpeed(float)         - Speed multiplier 0.25 to 4.0 (default 1.0)
 */

// 1. Configure provider and HTTP stack via shared example bootstrap.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

echo "=== Example 16: Text-to-Speech (TTS) ===\n\n";

// ── 1. Basic TTS: text → mp3 with the "nova" voice ────────────────────────────
echo "--- 1. Basic TTS (nova voice, mp3) ---\n";

$text = 'Welcome to Nexus AI. This library provides a unified, elegant interface
for interacting with every major AI provider through a single fluent API.';

try {
    $response = NexusAI::using('openai', 'tts-1')
        ->withPrompt($text)
        ->withVoice('nova')
        ->asSpeech();

    $outputPath = sys_get_temp_dir() . '/nexus-ai-speech-nova.mp3';
    file_put_contents($outputPath, $response->audioContent);

    echo "Audio generated successfully!\n";
    echo "Voice:       nova\n";
    echo "Format:      " . $response->format . "\n";
    echo "Size (KB):   " . round(strlen($response->audioContent) / 1024, 1) . "\n";
    echo "Saved to:    " . $outputPath . "\n\n";

} catch (\LogicException $e) {
    echo "Configuration error: " . $e->getMessage() . "\n\n";
} catch (\Exception $e) {
    echo "API error: " . $e->getMessage() . "\n\n";
}

// ── 2. HD quality with a different voice ──────────────────────────────────────
echo "--- 2. HD quality (tts-1-hd, onyx voice) ---\n";

try {
    $response = NexusAI::using('openai', 'tts-1-hd')
        ->withPrompt('Chapter one. The dark matter began to stir at the edge of the known universe.')
        ->withVoice('onyx')
        ->asSpeech();

    $outputPath = sys_get_temp_dir() . '/nexus-ai-speech-onyx-hd.mp3';
    file_put_contents($outputPath, $response->audioContent);

    echo "HD audio generated!\n";
    echo "Voice:    onyx (HD)\n";
    echo "Size (KB): " . round(strlen($response->audioContent) / 1024, 1) . "\n";
    echo "Saved to: " . $outputPath . "\n\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ── 3. Opus format for efficient streaming ────────────────────────────────────
echo "--- 3. Opus format (best for streaming/WebRTC) ---\n";

try {
    $response = NexusAI::using('openai', 'tts-1')
        ->withPrompt('Opus is an open, royalty-free, highly versatile audio codec optimized for streaming.')
        ->withVoice('alloy')
        ->withAudioFormat('opus')
        ->asSpeech();

    $outputPath = sys_get_temp_dir() . '/nexus-ai-speech.opus';
    file_put_contents($outputPath, $response->audioContent);

    echo "Opus audio generated!\n";
    echo "Format:   " . $response->format . "\n";
    echo "Size (KB): " . round(strlen($response->audioContent) / 1024, 1) . " (Opus is ~3x smaller than mp3)\n";
    echo "Saved to: " . $outputPath . "\n\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ── 4. Adjusted speed (great for learning / accessibility) ────────────────────
echo "--- 4. Slow speech for language learners (0.75x speed) ---\n";

try {
    $response = NexusAI::using('openai', 'tts-1')
        ->withPrompt('Bonjour! Comment allez-vous aujourd\'hui? Je m\'appelle Claude.')
        ->withVoice('shimmer')
        ->withSpeed(0.75)
        ->asSpeech();

    $outputPath = sys_get_temp_dir() . '/nexus-ai-speech-slow.mp3';
    file_put_contents($outputPath, $response->audioContent);

    echo "Slow-speed audio generated!\n";
    echo "Speed: 0.75x | Voice: shimmer\n";
    echo "Saved to: " . $outputPath . "\n\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ── 5. All available voices showcase ─────────────────────────────────────────
echo "--- 5. Voices available in OpenAI TTS ---\n";

$voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
$sampleText = 'Hello! I am the %s voice from OpenAI.';

foreach ($voices as $voice) {
    try {
        $response = NexusAI::using('openai', 'tts-1')
            ->withPrompt(sprintf($sampleText, $voice))
            ->withVoice($voice)
            ->asSpeech();

        $path = sys_get_temp_dir() . "/nexus-ai-voice-{$voice}.mp3";
        file_put_contents($path, $response->audioContent);
        echo "  ✔ {$voice}: " . round(strlen($response->audioContent) / 1024, 1) . " KB → {$path}\n";

    } catch (\Exception $e) {
        echo "  ✖ {$voice}: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ── 6. Error: missing voice ───────────────────────────────────────────────────
echo "--- 6. Graceful error: missing voice ---\n";

try {
    $response = NexusAI::using('openai', 'tts-1')
        ->withPrompt('This will fail because no voice is set.')
        ->asSpeech(); // Missing ->withVoice()

} catch (\LogicException $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}




