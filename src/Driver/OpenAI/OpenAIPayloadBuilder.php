<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\OpenAI;

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
use Token27\NexusAI\ValueObject\ToolCall;
use Token27\NexusAI\ValueObject\ToolResult;

/**
 * Transforms NexusAI domain objects into OpenAI API payload format.
 *
 * Stateless class with static methods. Handles the mapping of:
 * - Messages (System, User, Assistant, ToolCall, ToolResult) → OpenAI format
 * - ContentBlocks (Text, Image URL/Base64, Audio) → OpenAI multi-modal format
 * - Tools (ToolInterface) → OpenAI function calling format
 * - ToolChoice enum → OpenAI tool_choice string
 *
 * Inspired by Prism's separate Map classes (MessageMap, ToolMap, ToolChoiceMap)
 * but consolidated into a single class for simplicity.
 *
 * @see \Token27\NexusAI\Driver\OpenAI\OpenAIDriver
 * @see \Token27\NexusAI\Request\TextRequest
 */
final class OpenAIPayloadBuilder
{
    /**
     * Builds the complete API payload for a text (chat completion) request.
     *
     * @param TextRequest $request The text request with messages and parameters.
     * @param bool $stream Whether to enable streaming mode.
     * @return array<string, mixed> The complete payload ready for json_encode().
     */
    public static function buildTextPayload(TextRequest $request, bool $stream = false): array
    {
        $payload = [
            'model' => $request->model,
            'messages' => self::mapMessages($request->resolveMessages()),
        ];

        // Generation parameters (only include if explicitly set)
        if ($request->maxTokens !== null) {
            $payload['max_completion_tokens'] = $request->maxTokens;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->topP !== null) {
            $payload['top_p'] = $request->topP;
        }

        if ($stream) {
            $payload['stream'] = true;
            // Request usage stats in stream mode (OpenAI specific)
            $payload['stream_options'] = ['include_usage' => true];
        }

        // Tools
        if ($request->tools !== []) {
            $payload['tools'] = self::mapTools($request->tools);

            if ($request->toolChoice !== null) {
                $payload['tool_choice'] = self::mapToolChoice($request->toolChoice);
            }
        }

        // Passthrough provider-specific options
        foreach ($request->options as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * Maps NexusAI Message objects to the OpenAI messages array format.
     *
     * Mapping rules:
     * - SystemMessage       → {role: 'system', content: 'text'}
     * - UserMessage (text)  → {role: 'user', content: 'text'}
     * - UserMessage (multi) → {role: 'user', content: [{type: 'text', ...}, {type: 'image_url', ...}]}
     * - AssistantMessage    → {role: 'assistant', content: 'text'}
     * - ToolCallMessage     → {role: 'assistant', tool_calls: [...]}
     * - ToolResultMessage   → One {role: 'tool', ...} per result
     *
     * @param array<Message> $messages NexusAI message objects.
     * @return array<int, array<string, mixed>> OpenAI-formatted messages.
     */
    public static function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            match (true) {
                $message instanceof ToolResultMessage => self::mapToolResultMessage($message, $mapped),
                $message instanceof ToolCallMessage => self::mapToolCallMessage($message, $mapped),
                $message instanceof AssistantMessage => self::mapAssistantMessage($message, $mapped),
                $message instanceof SystemMessage => self::mapSystemMessage($message, $mapped),
                $message instanceof UserMessage => self::mapUserMessage($message, $mapped),
                default => throw new \InvalidArgumentException(
                    'Unsupported message type: ' . $message::class,
                ),
            };
        }

        return $mapped;
    }

    /**
     * Maps NexusAI ToolInterface objects to OpenAI function calling format.
     *
     * Each tool becomes:
     * {type: 'function', function: {name: '...', description: '...', parameters: {...}}}
     *
     * @param array<ToolInterface> $tools NexusAI tool definitions.
     * @return array<int, array<string, mixed>> OpenAI-formatted tool definitions.
     */
    public static function mapTools(array $tools): array
    {
        return array_map(
            fn (ToolInterface $tool): array => [
                'type' => 'function',
                'function' => array_filter([
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters() !== [] ? $tool->getParameters() : null,
                ]),
            ],
            $tools,
        );
    }

    /**
     * Maps NexusAI ToolChoice enum to OpenAI tool_choice format.
     *
     * Mapping:
     * - ToolChoice::Auto → 'auto'
     * - ToolChoice::Any  → 'required'
     * - ToolChoice::None → 'none'
     *
     * @param ToolChoice $choice The NexusAI tool choice.
     * @return string The OpenAI tool_choice value.
     */
    public static function mapToolChoice(ToolChoice $choice): string
    {
        return match ($choice) {
            ToolChoice::Auto => 'auto',
            ToolChoice::Any => 'required',
            ToolChoice::None => 'none',
        };
    }

    /**
     * Maps a SystemMessage to OpenAI format and appends to the output array.
     *
     * @param SystemMessage $message The system message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapSystemMessage(SystemMessage $message, array &$mapped): void
    {
        $mapped[] = [
            'role' => 'system',
            'content' => $message->getText(),
        ];
    }

    /**
     * Maps a UserMessage to OpenAI format.
     *
     * Plain text messages use simple string content.
     * Multi-modal messages use the content array format with typed parts.
     *
     * @param UserMessage $message The user message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapUserMessage(UserMessage $message, array &$mapped): void
    {
        $content = $message->getContent();

        // Plain text content
        if (is_string($content)) {
            $mapped[] = [
                'role' => 'user',
                'content' => $content,
            ];

            return;
        }

        // Multi-modal content blocks
        $parts = [];
        foreach ($content as $block) {
            $parts[] = self::mapContentBlock($block);
        }

        $mapped[] = [
            'role' => 'user',
            'content' => $parts,
        ];
    }

    /**
     * Maps an AssistantMessage to OpenAI format.
     *
     * If the message has tool calls, they are included in the tool_calls field.
     *
     * @param AssistantMessage $message The assistant message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapAssistantMessage(AssistantMessage $message, array &$mapped): void
    {
        $entry = [
            'role' => 'assistant',
            'content' => $message->getText(),
        ];

        if ($message->hasToolCalls()) {
            $entry['tool_calls'] = array_map(
                fn (ToolCall $tc): array => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => json_encode($tc->arguments, JSON_THROW_ON_ERROR),
                    ],
                ],
                $message->getToolCalls(),
            );
        }

        $mapped[] = $entry;
    }

    /**
     * Maps a ToolCallMessage to OpenAI format.
     *
     * ToolCallMessage is a specialized AssistantMessage with only tool calls (no text).
     *
     * @param ToolCallMessage $message The tool call message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapToolCallMessage(ToolCallMessage $message, array &$mapped): void
    {
        $mapped[] = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => array_map(
                fn (ToolCall $tc): array => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => json_encode($tc->arguments, JSON_THROW_ON_ERROR),
                    ],
                ],
                $message->getToolCalls(),
            ),
        ];
    }

    /**
     * Maps a ToolResultMessage to OpenAI format.
     *
     * OpenAI requires ONE message per tool result, each with role 'tool'
     * and the corresponding tool_call_id.
     *
     * @param ToolResultMessage $message The tool result message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapToolResultMessage(ToolResultMessage $message, array &$mapped): void
    {
        foreach ($message->results as $result) {
            $mapped[] = [
                'role' => 'tool',
                'tool_call_id' => $result->callId,
                'content' => $result->result,
            ];
        }
    }

    /**
     * Maps a single ContentBlock to the OpenAI content part format.
     *
     * Mapping:
     * - Text          → {type: 'text', text: '...'}
     * - Image (url)   → {type: 'image_url', image_url: {url: '...'}}
     * - Image (base64) → {type: 'image_url', image_url: {url: 'data:mime;base64,...'}}
     * - Audio         → {type: 'input_audio', input_audio: {data: '...', format: '...'}}
     *
     * @param ContentBlock $block The content block to map.
     * @return array<string, mixed> OpenAI content part.
     */
    private static function mapContentBlock(ContentBlock $block): array
    {
        return match ($block->type) {
            ContentType::Text => [
                'type' => 'text',
                'text' => $block->content,
            ],
            ContentType::Image => match ($block->sourceType) {
                'base64' => [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => sprintf('data:%s;base64,%s', $block->mediaType, $block->content),
                    ],
                ],
                default => [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $block->content,
                    ],
                ],
            },
            ContentType::Audio => [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => $block->content,
                    'format' => $block->mediaType ?? 'mp3',
                ],
            ],
            default => [
                'type' => 'text',
                'text' => $block->content,
            ],
        };
    }
}
