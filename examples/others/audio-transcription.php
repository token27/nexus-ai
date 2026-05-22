<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example 17: Audio Transcription (Speech-to-Text / STT)
 *
 * Demonstrates how to transcribe audio files into text using asTranscription().
 * The driver must implement AudioCapableInterface (e.g., OpenAI Whisper).
 *
 * Fluent setters for transcription:
 *   ->withAudioContent(string $bytes, string $filename) - Raw audio bytes + filename (required)
 *   ->withLanguage(string)                              - ISO-639-1 hint ('en', 'es', 'fr'...)
 *   ->withTranscriptionResponseFormat(string)           - 'json', 'text', 'verbose_json'
 */

// 1. Configure provider and HTTP stack via shared example bootstrap.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

echo "=== Example 17: Audio Transcription (Speech-to-Text) ===\n\n";

// ── 1. Basic transcription from an MP3 file ───────────────────────────────────
echo "--- 1. Basic transcription (auto language detection) ---\n";

$audioFile = __DIR__ . '/assets/sample-audio.mp3'; // Replace with a real audio file

if (!file_exists($audioFile)) {
    // Create a dummy placeholder for demonstration purposes
    echo "[NOTE] File '{$audioFile}' not found. Using placeholder bytes.\n";
    echo "[NOTE] In production: \$audioContent = file_get_contents('/path/to/audio.mp3');\n\n";
    $audioContent = str_repeat("\x00", 1024); // dummy bytes
    $audioFilename = 'sample-audio.mp3';
} else {
    $audioContent = file_get_contents($audioFile);
    $audioFilename = basename($audioFile);
}

try {
    $response = NexusAI::using('openai', 'whisper-1')
        ->withAudioContent($audioContent, $audioFilename)
        ->asTranscription();

    echo "Transcription complete!\n";
    echo "Language detected: " . ($response->language ?? 'auto') . "\n";
    echo "Duration: " . ($response->duration !== null ? round($response->duration, 2) . 's' : 'N/A') . "\n";
    echo "Text:\n  " . $response->text . "\n\n";

} catch (\LogicException $e) {
    echo "Configuration error: " . $e->getMessage() . "\n\n";
} catch (\Exception $e) {
    echo "API error: " . $e->getMessage() . "\n\n";
}

// ── 2. Transcription with explicit language hint ──────────────────────────────
echo "--- 2. Spanish audio with language hint ---\n";

try {
    $response = NexusAI::using('openai', 'whisper-1')
        ->withAudioContent($audioContent, 'spanish-recording.mp3')
        ->withLanguage('es') // Providing the language improves accuracy and speed
        ->asTranscription();

    echo "Transcription (es):\n  " . $response->text . "\n\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ── 3. Verbose JSON for timestamped segments ──────────────────────────────────
echo "--- 3. Verbose JSON format (with timestamps) ---\n";

try {
    $response = NexusAI::using('openai', 'whisper-1')
        ->withAudioContent($audioContent, 'lecture.mp3')
        ->withLanguage('en')
        ->withTranscriptionResponseFormat('verbose_json')
        ->asTranscription();

    echo "Full transcription:\n  " . $response->text . "\n";
    echo "Duration: " . ($response->duration !== null ? round($response->duration, 2) . 's' : 'N/A') . "\n";

    if ($response->segments !== null && count($response->segments) > 0) {
        echo "Segments (" . count($response->segments) . " found):\n";
        foreach (array_slice($response->segments, 0, 3) as $segment) {
            // Each segment has: id, start, end, text
            $start = $segment['start'] ?? 0;
            $end = $segment['end'] ?? 0;
            $text = $segment['text'] ?? '';
            printf("  [%05.2fs → %05.2fs] %s\n", $start, $end, trim($text));
        }
        if (count($response->segments) > 3) {
            echo "  ... and " . (count($response->segments) - 3) . " more segments.\n";
        }
    }
    echo "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// ── 4. Transcribe from a WAV file ─────────────────────────────────────────────
echo "--- 4. WAV file transcription ---\n";

$wavFile = __DIR__ . '/assets/sample-audio.wav';

if (file_exists($wavFile)) {
    $wavContent = file_get_contents($wavFile);

    try {
        $response = NexusAI::using('openai', 'whisper-1')
            ->withAudioContent($wavContent, 'sample-audio.wav')
            ->withLanguage('en')
            ->asTranscription();

        echo "WAV transcription:\n  " . $response->text . "\n\n";

    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "[SKIP] sample-audio.wav not found. Whisper-1 supports: mp3, mp4, mpeg, mpga, m4a, wav, webm\n\n";
}

// ── 5. Transcribe from a URL (download first, then send bytes) ────────────────
echo "--- 5. Transcribe from a remote URL ---\n";
echo "[NOTE] Whisper API requires file upload; fetch the bytes first:\n\n";
echo <<<'CODE'
    // Download the audio
    $audioContent = file_get_contents('https://example.com/podcast-episode.mp3');

    $response = NexusAI::using('openai', 'whisper-1')
        ->withAudioContent($audioContent, 'podcast-episode.mp3')
        ->withLanguage('en')
        ->asTranscription();

    echo $response->text;
CODE;
echo "\n\n";

// ── 6. Error: missing audio content ──────────────────────────────────────────
echo "--- 6. Graceful error: missing audio content ---\n";

try {
    $response = NexusAI::using('openai', 'whisper-1')
        ->withLanguage('en')
        ->asTranscription(); // Missing ->withAudioContent()

} catch (\LogicException $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}




