<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example 18: Multi-Modal Combined Workflows
 *
 * Demonstrates real-world pipelines that chain multiple AI modalities together:
 *
 *  A) Describe → Imagine  : Ask the LLM to write a prompt, then generate the image.
 *  B) Read → Speak        : Summarize text with GPT-4, then narrate it with TTS.
 *  C) Speak → Transcribe  : Generate audio with TTS, then transcribe it back (loop test).
 *  D) Image → Describe    : Generate an image URL, feed it into a vision model.
 */

// 1. Configure provider and HTTP stack via shared example bootstrap.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

echo "=== Example 18: Multi-Modal Combined Workflows ===\n\n";

// ════════════════════════════════════════════════════════════════════
//  WORKFLOW A: Text → Optimised Prompt → Image
//  "Ask the LLM to craft a perfect image prompt, then generate it."
// ════════════════════════════════════════════════════════════════════
echo "━━━ WORKFLOW A: LLM-enhanced image generation ━━━\n";

try {
    // Step 1: Ask GPT-4o to write a high-quality DALL-E prompt
    $promptResponse = NexusAI::using('openai', 'gpt-4o')
        ->withSystemPrompt(
            'You are an expert at writing concise, vivid DALL-E 3 image prompts. ' .
            'Output ONLY the prompt, no explanation, no quotes.',
        )
        ->withPrompt('A cozy coffee shop on a rainy Paris street, warm interior lighting')
        ->withMaxTokens(120)
        ->withTemperature(0.8)
        ->asText();

    $optimisedPrompt = trim($promptResponse->text);

    echo "GPT-4o generated image prompt:\n  \"{$optimisedPrompt}\"\n\n";

    // Step 2: Use that prompt to generate the image
    $imageResponse = NexusAI::using('openai', 'dall-e-3')
        ->withPrompt($optimisedPrompt)
        ->withSize('1024x1024')
        ->withQuality('hd')
        ->withOutputFormat('png')
        ->asImage();

    $image = $imageResponse->images[0];
    echo "Image generated!\n";
    echo "URL:            " . ($image->url ?? 'N/A') . "\n";
    echo "Revised prompt: " . ($image->revisedPrompt ?? 'N/A') . "\n\n";

} catch (\Exception $e) {
    echo "Workflow A error: " . $e->getMessage() . "\n\n";
}

// ════════════════════════════════════════════════════════════════════
//  WORKFLOW B: Long Text → Summary → Audio Narration
//  "Summarise an article with GPT-4, then narrate it with TTS."
// ════════════════════════════════════════════════════════════════════
echo "━━━ WORKFLOW B: Article summary → Audio narration ━━━\n";

$longArticle = <<<'TEXT'
    Artificial intelligence is rapidly transforming every sector of the global economy.
    Healthcare systems are leveraging machine learning models to detect diseases earlier
    and with greater accuracy than ever before. In the automotive industry, self-driving
    vehicles are moving from research labs onto public roads. Financial institutions use
    AI for real-time fraud detection, risk assessment, and personalised investment advice.
    Education platforms deploy adaptive learning algorithms to tailor curricula to each
    student's pace and learning style. Despite these advances, concerns around job
    displacement, data privacy, and algorithmic bias remain central to public debate.
TEXT;

try {
    // Step 1: Summarise the article in 2 sentences
    $summaryResponse = NexusAI::using('openai', 'gpt-4o-mini')
        ->withSystemPrompt('Summarise the following text in exactly 2 clear, engaging sentences suitable for narration.')
        ->withPrompt($longArticle)
        ->withMaxTokens(100)
        ->withTemperature(0.4)
        ->asText();

    $summary = trim($summaryResponse->text);
    echo "GPT-4o-mini summary:\n  \"{$summary}\"\n\n";

    // Step 2: Convert the summary to speech
    $speechResponse = NexusAI::using('openai', 'tts-1-hd')
        ->withPrompt($summary)
        ->withVoice('nova')
        ->withAudioFormat('mp3')
        ->asSpeech();

    $outputPath = sys_get_temp_dir() . '/nexus-ai-narration.mp3';
    file_put_contents($outputPath, $speechResponse->audioContent);

    echo "Audio narration saved!\n";
    echo "Voice:    nova (HD)\n";
    echo "Size:     " . round(strlen($speechResponse->audioContent) / 1024, 1) . " KB\n";
    echo "Saved to: {$outputPath}\n\n";

} catch (\Exception $e) {
    echo "Workflow B error: " . $e->getMessage() . "\n\n";
}

// ════════════════════════════════════════════════════════════════════
//  WORKFLOW C: Text → TTS → Transcribe (Round-trip test)
//  "Generate audio with TTS, then transcribe back to text."
// ════════════════════════════════════════════════════════════════════
echo "━━━ WORKFLOW C: TTS → STT round-trip accuracy test ━━━\n";

$originalText = 'The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.';

try {
    // Step 1: Convert text to speech
    $speechResponse = NexusAI::using('openai', 'tts-1')
        ->withPrompt($originalText)
        ->withVoice('alloy')
        ->withAudioFormat('mp3')
        ->asSpeech();

    echo "TTS generated: " . round(strlen($speechResponse->audioContent) / 1024, 1) . " KB\n";

    // Step 2: Transcribe the generated audio back to text
    $transcriptionResponse = NexusAI::using('openai', 'whisper-1')
        ->withAudioContent($speechResponse->audioContent, 'round-trip-test.mp3')
        ->withLanguage('en')
        ->asTranscription();

    $transcribed = trim($transcriptionResponse->text);

    echo "Original:    \"{$originalText}\"\n";
    echo "Transcribed: \"{$transcribed}\"\n";

    // Simple accuracy check (word overlap)
    $originalWords = str_word_count(strtolower($originalText), 1);
    $transcribedWords = str_word_count(strtolower($transcribed), 1);
    $matchingWords = count(array_intersect($originalWords, $transcribedWords));
    $accuracy = round($matchingWords / count($originalWords) * 100, 1);

    echo "Word accuracy: {$accuracy}%\n\n";

} catch (\Exception $e) {
    echo "Workflow C error: " . $e->getMessage() . "\n\n";
}

// ════════════════════════════════════════════════════════════════════
//  WORKFLOW D: All modalities in a content pipeline
//  "User prompt → structured plan → image → narration"
// ════════════════════════════════════════════════════════════════════
echo "━━━ WORKFLOW D: Full content pipeline (Text + Image + Audio) ━━━\n";

$topic = 'The future of renewable energy';

try {
    // Step 1: Generate a short blog intro
    $introResponse = NexusAI::using('openai', 'gpt-4o')
        ->withSystemPrompt('Write a single compelling introductory sentence for a blog post. No quotes.')
        ->withPrompt("Topic: {$topic}")
        ->withMaxTokens(60)
        ->withTemperature(0.7)
        ->asText();

    $intro = trim($introResponse->text);
    echo "1. Blog intro: \"{$intro}\"\n";

    // Step 2: Generate a matching hero image
    $imageResponse = NexusAI::using('openai', 'dall-e-3')
        ->withPrompt("Hero image for a blog post about: {$topic}. "
            . 'Dramatic wide shot, editorial photography style, optimistic tone.')
        ->withSize('1792x1024')
        ->withQuality('hd')
        ->asImage();

    $heroImage = $imageResponse->images[0];
    echo "2. Hero image: " . ($heroImage->url ?? 'Generated (b64)') . "\n";

    // Step 3: Narrate the intro sentence
    $narrationResponse = NexusAI::using('openai', 'tts-1')
        ->withPrompt($intro)
        ->withVoice('shimmer')
        ->withAudioFormat('mp3')
        ->asSpeech();

    $audioPath = sys_get_temp_dir() . '/nexus-ai-blog-narration.mp3';
    file_put_contents($audioPath, $narrationResponse->audioContent);

    echo "3. Audio intro: " . round(strlen($narrationResponse->audioContent) / 1024, 1) . " KB → {$audioPath}\n\n";
    echo "Pipeline complete! ✔\n\n";

} catch (\Exception $e) {
    echo "Workflow D error: " . $e->getMessage() . "\n\n";
}

echo "=== All workflows finished ===\n";




