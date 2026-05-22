<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver\Anthropic;

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
 * Transforms NexusAI domain objects into Anthropic Messages API payload format.
 *
 * Stateless class with static methods. Key differences from OpenAI:
 * - System prompt goes in a separate top-level `system` field (NOT in messages array)
 * - `max_tokens` is MANDATORY (Anthropic returns 400 without it)
 * - Tool calls use `tool_use`/`tool_result` format (not `function`)
 * - Images use `{type: 'image', source: {type: 'base64', ...}}` format
 * - Tool results go inside a `user` role message with `tool_result` content blocks
 *
 * @see \Token27\NexusAI\Driver\Anthropic\AnthropicDriver
 * @see \Token27\NexusAI\Request\TextRequest
 */
final class AnthropicPayloadBuilder
{
    /** Default max_tokens when none is specified. Anthropic requires this field. */
    private const int DEFAULT_MAX_TOKENS = 4096;

    /**
     * Builds the complete API payload for an Anthropic Messages request.
     *
     * @param TextRequest $request The text request with messages and parameters.
     * @param bool $stream Whether to enable streaming mode.
     * @return array<string, mixed> The complete payload ready for json_encode().
     */
    public static function buildTextPayload(TextRequest $request, bool $stream = false): array
    {
        $allMessages = $request->resolveMessages();

        // Extract system messages — they go in the top-level 'system' field
        $systemText = self::extractSystemPrompt($allMessages);
        $nonSystemMessages = array_values(array_filter(
            $allMessages,
            fn (Message $m): bool => !$m instanceof SystemMessage,
        ));

        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens ?? self::DEFAULT_MAX_TOKENS,
            'messages' => self::mapMessages($nonSystemMessages),
        ];

        // System prompt as top-level field (NOT in messages)
        if ($systemText !== null) {
            $payload['system'] = $systemText;
        }

        // Generation parameters
        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->topP !== null) {
            $payload['top_p'] = $request->topP;
        }

        if ($stream) {
            $payload['stream'] = true;
        }

        // Tools — only if tool choice is not None
        if ($request->tools !== [] && $request->toolChoice !== ToolChoice::None) {
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
     * Maps NexusAI Message objects to the Anthropic messages array format.
     *
     * CRITICAL differences from OpenAI:
     * - SystemMessage is EXCLUDED (handled separately via top-level 'system' field)
     * - ToolCallMessage → {role: 'assistant', content: [{type: 'tool_use', ...}]}
     * - ToolResultMessage → {role: 'user', content: [{type: 'tool_result', ...}]}
     *
     * @param array<Message> $messages NexusAI message objects (without SystemMessage).
     * @return array<int, array<string, mixed>> Anthropic-formatted messages.
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
     * Maps NexusAI ToolInterface objects to Anthropic tool format.
     *
     * Anthropic uses a simpler format than OpenAI (no 'type: function' wrapper):
     * {name: '...', description: '...', input_schema: {...}}
     *
     * @param array<ToolInterface> $tools NexusAI tool definitions.
     * @return array<int, array<string, mixed>> Anthropic-formatted tool definitions.
     */
    public static function mapTools(array $tools): array
    {
        return array_map(
            fn (ToolInterface $tool): array => array_filter([
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema' => $tool->getParameters() !== []
                    ? array_merge(['type' => 'object'], $tool->getParameters())
                    : ['type' => 'object', 'properties' => new \stdClass()],
            ]),
            $tools,
        );
    }

    /**
     * Maps NexusAI ToolChoice enum to Anthropic tool_choice format.
     *
     * Mapping:
     * - ToolChoice::Auto → {type: 'auto'}
     * - ToolChoice::Any  → {type: 'any'}
     * - ToolChoice::None → Not sent (handled in buildTextPayload by not including tools)
     *
     * @param ToolChoice $choice The NexusAI tool choice.
     * @return array<string, string> The Anthropic tool_choice value.
     */
    public static function mapToolChoice(ToolChoice $choice): array
    {
        return match ($choice) {
            ToolChoice::Auto => ['type' => 'auto'],
            ToolChoice::Any => ['type' => 'any'],
            ToolChoice::None => ['type' => 'auto'], // Fallback; tools are not sent when None
        };
    }

    /**
     * Extracts and concatenates all system prompts from the messages array.
     *
     * Anthropic requires the system prompt in a separate top-level field.
     * Multiple system messages are concatenated with newlines.
     *
     * @param array<Message> $messages All messages including system messages.
     * @return string|null Concatenated system prompt, or null if none.
     */
    private static function extractSystemPrompt(array $messages): ?string
    {
        $systemParts = [];

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $systemParts[] = $message->getText();
            }
        }

        return $systemParts !== [] ? implode("\n", $systemParts) : null;
    }

    /**
     * Maps a UserMessage to Anthropic format.
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
     * Maps an AssistantMessage to Anthropic format.
     *
     * @param AssistantMessage $message The assistant message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapAssistantMessage(AssistantMessage $message, array &$mapped): void
    {
        $mapped[] = [
            'role' => 'assistant',
            'content' => $message->getText(),
        ];
    }

    /**
     * Maps a ToolCallMessage to Anthropic format.
     *
     * Anthropic represents tool calls as content blocks with type 'tool_use':
     * {role: 'assistant', content: [{type: 'tool_use', id: '...', name: '...', input: {...}}]}
     *
     * @param ToolCallMessage $message The tool call message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapToolCallMessage(ToolCallMessage $message, array &$mapped): void
    {
        $content = [];

        foreach ($message->getToolCalls() as $tc) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $tc->id,
                'name' => $tc->name,
                'input' => $tc->arguments !== [] ? $tc->arguments : new \stdClass(),
            ];
        }

        $mapped[] = [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    /**
     * Maps a ToolResultMessage to Anthropic format.
     *
     * Anthropic requires tool results as content blocks inside a 'user' role message:
     * {role: 'user', content: [{type: 'tool_result', tool_use_id: '...', content: '...'}]}
     *
     * Unlike OpenAI which uses ONE message per result with role 'tool',
     * Anthropic groups ALL results into a single 'user' message.
     *
     * @param ToolResultMessage $message The tool result message.
     * @param array<int, array<string, mixed>> &$mapped Output array (by reference).
     */
    private static function mapToolResultMessage(ToolResultMessage $message, array &$mapped): void
    {
        $content = [];

        foreach ($message->results as $result) {
            $block = [
                'type' => 'tool_result',
                'tool_use_id' => $result->callId,
                'content' => $result->result,
            ];

            if ($result->isError) {
                $block['is_error'] = true;
            }

            $content[] = $block;
        }

        $mapped[] = [
            'role' => 'user',
            'content' => $content,
        ];
    }

    /**
     * Maps a single ContentBlock to the Anthropic content part format.
     *
     * Mapping:
     * - Text          → {type: 'text', text: '...'}
     * - Image (base64) → {type: 'image', source: {type: 'base64', media_type: '...', data: '...'}}
     * - Image (url)   → {type: 'image', source: {type: 'url', url: '...'}}
     *
     * @param ContentBlock $block The content block to map.
     * @return array<string, mixed> Anthropic content part.
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
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $block->mediaType ?? 'image/png',
                        'data' => $block->content,
                    ],
                ],
                default => [
                    'type' => 'image',
                    'source' => [
                        'type' => 'url',
                        'url' => $block->content,
                    ],
                ],
            },
            default => [
                'type' => 'text',
                'text' => $block->content,
            ],
        };
    }
}
