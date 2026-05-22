<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Driver\OpenAI;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Driver\OpenAI\OpenAIResponseParser;

final class OpenAIResponseParserTest extends TestCase
{
    public function testParseTextResponseSupportsChatCompletionsUsageFormat(): void
    {
        $data = [
            'id' => 'chatcmpl-test',
            'model' => 'gpt-4o-2024-08-06',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 44,
                'completion_tokens' => 133,
                'total_tokens' => 177,
                'prompt_tokens_details' => [
                    'cached_tokens' => 0,
                ],
            ],
        ];

        $response = OpenAIResponseParser::parseTextResponse($data);

        $this->assertSame(44, $response->usage->textInputTokens);
        $this->assertSame(133, $response->usage->textOutputTokens);
        $this->assertSame(177, $response->usage->totalTokens());
    }

    public function testParseTextResponseSupportsNewOpenAIBreakdownFormat(): void
    {
        $data = [
            'id' => 'chatcmpl-test',
            'model' => 'gpt-4o',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'input_tokens_details' => [
                    'text_tokens' => 16,
                    'image_tokens' => 0,
                ],
                'output_tokens_details' => [
                    'text_tokens' => 272,
                    'image_tokens' => 0,
                ],
            ],
        ];

        $response = OpenAIResponseParser::parseTextResponse($data);

        $this->assertSame(16, $response->usage->textInputTokens);
        $this->assertSame(272, $response->usage->textOutputTokens);
        $this->assertSame(288, $response->usage->totalTokens());
    }
}
