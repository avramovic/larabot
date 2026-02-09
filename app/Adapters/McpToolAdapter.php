<?php

namespace App\Adapters;

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
    public function __construct(protected Tool $tool)
    {

    }

    public function toLlmTool(): CallbackToolDefinition
    {
        $inputSchema = [
            'type' => 'object',
            'properties' => $this->getToolInputSchema(),
        ];

        return new CallbackToolDefinition(
            name: $this->tool->name(),
            description: $this->tool->description(),
            inputSchema: $inputSchema,
            handler: function (array $input): LLMMessageContents {
                /** @var Response|ResponseFactory $response */
                try {
                    $response = $this->tool->handle(new \Laravel\Mcp\Request($input));

                    if ($structured = $response->getStructuredContent()) {
                        return LLMMessageContents::fromArrayData($structured);
                    }

                    return LLMMessageContents::fromString($response->content());
                } catch (\Exception $exception) {
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
