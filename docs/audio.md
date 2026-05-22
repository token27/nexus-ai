# Audio (TTS & STT)

NexusAI supports Text-to-Speech (TTS) and Speech-to-Text (STT/Transcription) via `AudioCapableInterface`.

---

## Text-to-Speech (TTS)

Convert text into natural-sounding audio.

### Basic Usage

```php
use Token27\NexusAI\Request\SpeechRequest;

$request = new SpeechRequest(
    provider: 'openai',
    model: 'tts-1',
    input: 'Hello! Welcome to NexusAI. Your AI communication engine.',
    voice: 'alloy',
);

$driver = /* resolve AudioCapableInterface driver */;
$response = $driver->speak($request);

// Save to file
file_put_contents('welcome.mp3', $response->audioContent);
```

### Full Parameters

```php
$request = new SpeechRequest(
    provider: 'openai',
    model: 'tts-1-hd',         // 'tts-1' (faster) | 'tts-1-hd' (higher quality)
    input: 'Your text here.',
    voice: 'nova',              // See voices below
    responseFormat: 'mp3',     // 'mp3' | 'opus' | 'aac' | 'flac'
    speed: 1.0,                 // 0.25 to 4.0
);
```

### Available Voices

| Voice | Personality |
|-------|-------------|
| `alloy` | Neutral, balanced |
| `echo` | Mature, authoritative |
| `fable` | Expressive, storytelling |
| `onyx` | Deep, formal |
| `nova` | Friendly, upbeat |
| `shimmer` | Soft, gentle |

### Response Object

```php
$response->audioContent;  // string — raw audio bytes
$response->format;        // string — 'mp3', 'opus', etc.
$response->meta;          // Meta
```

### Stream Audio to Browser

```php
header('Content-Type: audio/mpeg');
header('Content-Disposition: inline; filename="speech.mp3"');

$request = new SpeechRequest(
    provider: 'openai',
    model: 'tts-1',
    input: $_POST['text'],
    voice: 'nova',
);

$response = $driver->speak($request);
echo $response->audioContent;
```

### Save with Different Formats

```php
// FLAC — lossless, best for archiving
$request = new SpeechRequest('openai', 'tts-1-hd', 'Text', 'alloy', 'flac');
file_put_contents('audio.flac', $driver->speak($request)->audioContent);

// OPUS — best for streaming/podcasts
$request = new SpeechRequest('openai', 'tts-1', 'Text', 'alloy', 'opus');
file_put_contents('audio.opus', $driver->speak($request)->audioContent);
```

---

## Speech-to-Text / Transcription (STT)

Transcribe audio files to text using Whisper or compatible models.

### Basic Usage

```php
use Token27\NexusAI\Request\TranscriptionRequest;

$audioContent = file_get_contents('/path/to/recording.mp3');

$request = new TranscriptionRequest(
    provider: 'openai',
    model: 'whisper-1',
    audioContent: $audioContent,
    audioFilename: 'recording.mp3',
);

$response = $driver->transcribe($request);
echo $response->text; // "Hello, this is my transcription..."
```

### Full Parameters

```php
$request = new TranscriptionRequest(
    provider: 'openai',
    model: 'whisper-1',
    audioContent: file_get_contents('interview.mp3'),
    audioFilename: 'interview.mp3',
    language: 'es',           // ISO-639-1 code. null = auto-detect
    responseFormat: 'json',   // 'json' | 'text' | 'verbose_json'
);
```

### Response Object

```php
$response->text;      // string — the transcribed text
$response->language;  // string|null — detected language ('en', 'es', ...)
$response->duration;  // float|null — audio duration in seconds (verbose_json)
$response->segments;  // array|null — word-level timestamps (verbose_json)
$response->meta;      // Meta
```

### Supported Audio Formats

| Format | Extension | Notes |
|--------|-----------|-------|
| MP3 | `.mp3` | Most common |
| MP4 Audio | `.mp4`, `.m4a` | iPhone recordings |
| MPEG | `.mpeg` | |
| MPGA | `.mpga` | |
| WAV | `.wav` | Uncompressed |
| WEBM | `.webm` | Web recordings |
| FLAC | `.flac` | Lossless |

> Max file size: **25 MB** for OpenAI Whisper.

### Transcribe with Language Hint (Faster)

```php
$request = new TranscriptionRequest(
    provider: 'openai',
    model: 'whisper-1',
    audioContent: file_get_contents('french_audio.mp3'),
    audioFilename: 'french_audio.mp3',
    language: 'fr', // Specify language → faster, more accurate
);
```

### Verbose Transcription with Timestamps

```php
$request = new TranscriptionRequest(
    provider: 'openai',
    model: 'whisper-1',
    audioContent: $audio,
    audioFilename: 'podcast.mp3',
    responseFormat: 'verbose_json',
);

$response = $driver->transcribe($request);
echo "Duration: {$response->duration}s\n";
foreach ($response->segments as $segment) {
    echo "[{$segment['start']}s - {$segment['end']}s] {$segment['text']}\n";
}
```

## Provider Support

| Provider | TTS | STT | Notes |
|----------|-----|-----|-------|
| OpenAI | ✅ tts-1, tts-1-hd | ✅ whisper-1 | Full support |
| Anthropic | ❌ | ❌ | Not supported |
| Gemini | ⚠️ | ⚠️ | Planned |
| Ollama | ❌ | ❌ | Not supported |

> Check `$driver->supports('speech')` or `$driver->supports('transcription')` before calling.

---

> **Related:** [Text Generation](text-generation.md) · [Streaming](streaming.md)
