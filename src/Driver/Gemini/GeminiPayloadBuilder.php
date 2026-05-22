<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Gemini;

use Token27\NexusAI\Contract\ToolInterface;
use Token27\NexusAI\Enum\ContentType;
use Token27\NexusAI\Enum\ToolChoice;
use Token27\NexusAI\Message\AssistantMessage;
use Token27\NexusAI\Message\Message;
use Token27\NexusAI\Message\SystemMessage;
use Token27\NexusAI\Message\ToolCallMessage;
use Token27\NexusAI\Message\ToolResultMessage;
use Token27\NexusAI\Message\UserMessage;
use Token27\NexusAI\Request\TextRequest;
use Token27\NexusAI\ValueObject\ContentBlock;

/**
 * Transforms NexusAI domain objects into Gemini API payload format.
 *
 * Key differences from OpenAI:
 * - System prompt → top-level `systemInstruction` field (not in contents)
 * - Messages → `contents` array with roles `user`/`model` (NOT `assistant`)
 * - Content uses `parts` array (not `content` string)
 * - Tools → `functionDeclarations` (not `function` type wrapper)
 * - Tool calls → `functionCall` / `functionResponse`
 * - Generation config → separate `generationConfig` object
 *
 * @see \Token27\NexusAI\Driver\Gemini\GeminiDriver
 */
final class GeminiPayloadBuilder
{
    /**
     * Builds the complete API payload for a Gemini generateContent request.
     *
     * @param TextRequest $request The text request.
     * @param bool $stream Whether streaming (affects endpoint, not payload).
     * @return array<string, mixed> The complete payload.
     */
    public static function buildTextPayload(TextRequest $request, bool $stream = false): array
    {
        $allMessages = $request->resolveMessages();

        // Extract system prompt → systemInstruction
        $systemText = self::extractSystemPrompt($allMessages);
        $nonSystemMessages = array_values(array_filter(
            $allMessages,
            fn (Message $m): bool => !$m instanceof SystemMessage,
        ));

        $payload = [
            'contents' => self::mapMessages($nonSystemMessages),
        ];

        // System instruction as top-level field
        if ($systemText !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemText]],
            ];
        }

        // Generation config
        $generationConfig = [];
        if ($request->maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $request->maxTokens;
        }
        if ($request->temperature !== null) {
            $generationConfig['temperature'] = $request->temperature;
        }
        if ($request->topP !== null) {
            $generationConfig['topP'] = $request->topP;
        }
        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        // Tools
        if ($request->tools !== [] && $request->toolChoice !== ToolChoice::None) {
            $payload['tools'] = [
                ['functionDeclarations' => self::mapTools($request->tools)],
            ];
        }

        // Passthrough provider-specific options
        foreach ($request->options as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * Maps NexusAI Message objects to Gemini contents array.
     *
     * CRITICAL: Gemini uses `model` for assistant role (NOT `assistant`).
     *
     * @param array<Message> $messages NexusAI messages (without SystemMessage).
     * @return array<int, array<string, mixed>> Gemini-formatted contents.
     */
    public static function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            match (true) {
                $message instanceof ToolResultMessage => self::mapToolResultMessage($message, $mapped),
                $message instanceof ToolCallMessage => self::mapToolCallMessage($message, $mapped),
                $message instanceof AssistantMessage => self::mapAssistantMessage($message, $mapped),
                $message instanceof SystemMessage => null, // Skip — handled at top level
                $message instanceof UserMessage => self::mapUserMessage($message, $mapped),
                default => throw new \InvalidArgumentException(
                    'Unsupported message type: ' . $message::class,
                ),
            };
        }

        return $mapped;
    }

    /**
     * Maps tools to Gemini functionDeclarations format.
     *
     * @param array<ToolInterface> $tools NexusAI tool definitions.
     * @return array<int, array<string, mixed>> Gemini function declarations.
     */
    public static function mapTools(array $tools): array
    {
        return array_map(
            fn (ToolInterface $tool): array => array_filter([
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters() !== []
                    ? array_merge(['type' => 'object'], $tool->getParameters())
                    : null,
            ]),
            $tools,
        );
    }

    /**
     * Extracts system prompt text from messages.
     *
     * @param array<Message> $messages All messages.
     * @return string|null Concatenated system prompt, or null.
     */
    private static function extractSystemPrompt(array $messages): ?string
    {
        $parts = [];
        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $parts[] = $message->getText();
            }
        }

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    /**
     * Maps UserMessage to Gemini format.
     *
     * @param UserMessage $message The user message.
     * @param array<int, array<string, mixed>> &$mapped Output array.
     */
    private static function mapUserMessage(UserMessage $message, array &$mapped): void
    {
        $content = $message->getContent();

        if (is_string($content)) {
            $mapped[] = [
                'role' => 'user',
                'parts' => [['text' => $content]],
            ];
            return;
        }

        // Multi-modal
        $parts = [];
        foreach ($content as $block) {
            $parts[] = self::mapContentBlock($block);
        }

        $mapped[] = [
            'role' => 'user',
            'parts' => $parts,
        ];
    }

    /**
     * Maps AssistantMessage to Gemini format. Uses role `model`.
     *
     * @param AssistantMessage $message The assistant message.
     * @param array<int, array<string, mixed>> &$mapped Output array.
     */
    private static function mapAssistantMessage(AssistantMessage $message, array &$mapped): void
    {
        $mapped[] = [
            'role' => 'model',
            'parts' => [['text' => $message->getText()]],
        ];
    }

    /**
     * Maps ToolCallMessage to Gemini format using functionCall parts.
     *
     * @param ToolCallMessage $message The tool call message.
     * @param array<int, array<string, mixed>> &$mapped Output array.
     */
    private static function mapToolCallMessage(ToolCallMessage $message, array &$mapped): void
    {
        $parts = [];
        foreach ($message->getToolCalls() as $tc) {
            $parts[] = [
                'functionCall' => [
                    'name' => $tc->name,
                    'args' => $tc->arguments !== [] ? $tc->arguments : new \stdClass(),
                ],
            ];
        }

        $mapped[] = [
            'role' => 'model',
            'parts' => $parts,
        ];
    }

    /**
     * Maps ToolResultMessage to Gemini format using functionResponse parts.
     *
     * @param ToolResultMessage $message The tool result message.
     * @param array<int, array<string, mixed>> &$mapped Output array.
     */
    private static function mapToolResultMessage(ToolResultMessage $message, array &$mapped): void
    {
        $parts = [];
        foreach ($message->results as $result) {
            $parts[] = [
                'functionResponse' => [
                    'name' => $result->toolName,
                    'response' => ['result' => $result->result],
                ],
            ];
        }

        $mapped[] = [
            'role' => 'function',
            'parts' => $parts,
        ];
    }

    /**
     * Maps ContentBlock to Gemini part format.
     *
     * @param ContentBlock $block The content block.
     * @return array<string, mixed> Gemini part.
     */
    private static function mapContentBlock(ContentBlock $block): array
    {
        return match ($block->type) {
            ContentType::Text => ['text' => $block->content],
            ContentType::Image => match ($block->sourceType) {
                'base64' => [
                    'inlineData' => [
                        'mimeType' => $block->mediaType ?? 'image/png',
                        'data' => $block->content,
                    ],
                ],
                default => [
                    'fileData' => [
                        'mimeType' => $block->mediaType ?? 'image/png',
                        'fileUri' => $block->content,
                    ],
                ],
            },
            default => ['text' => $block->content],
        };
    }
}
