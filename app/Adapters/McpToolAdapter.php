<?php

namespace App\Adapters;

use App\Channels\ChatInterface;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\BooleanType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Soukicz\Llm\Message\LLMMessageContents;
use Soukicz\Llm\Tool\CallbackToolDefinition;

class McpToolAdapter
{
    protected ChatInterface $chat;
    public function __construct(protected Tool $tool)
    {
        $this->chat = app(ChatInterface::class);
    }

    public function toLlmTool(): CallbackToolDefinition
    {
        $inputSchema = [
            'type'       => 'object',
            'properties' => $this->getToolInputSchema(),
        ];

        return new CallbackToolDefinition(
            name: $this->tool->name(),
            description: $this->tool->description(),
            inputSchema: $inputSchema,
            handler: function (array $input): LLMMessageContents {
                /** @var Response|ResponseFactory $response */
                \Log::info("[TOOL CALL] {$this->tool->name()} tool called with params: ", $input);
                try {
                    $this->chat->sendChatAction();
                    $response = $this->tool->handle(new \Laravel\Mcp\Request($input));

                    if (method_exists($response, 'getStructuredContent') && $structured = $response->getStructuredContent()) {
                        \Log::debug("[TOOL RESPONSE] {$this->tool->name()} tool returned structured content: ", $structured);
                        return LLMMessageContents::fromArrayData($structured);
                    }

                    \Log::debug("[TOOL RESPONSE] {$this->tool->name()} tool returned unstructured content: " .  $response->content());

                    return LLMMessageContents::fromString($response->content());
                } catch (\Exception $exception) {
                    \Log::error("[TOOL CALL] {$this->tool->name()} tool execution failed: " . $exception->getMessage(), ['trace' => $exception->getTrace()]);
                    return LLMMessageContents::fromErrorString('Tool execution failed: ' . $exception->getMessage());
                }
            },
        );
    }


    public function getToolInputSchema(): array
    {
        return array_map(function (Type $type) {
            return $type->toArray();
        }, $this->tool->schema(new JsonSchemaTypeFactory()));
    }

    protected function getType(Type $type): string
    {
        return match (true) {
            $type instanceof IntegerType => 'integer',
            $type instanceof NumberType => 'number',
            $type instanceof BooleanType => 'boolean',
            $type instanceof ObjectType => 'object',
            $type instanceof ArrayType => 'array',
            default => 'string',
        };
    }
}
