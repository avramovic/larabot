<?php

namespace App\Mcp\Tools;

use App\Models\Memory;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class MemorySaveTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Add a memory which can be retrieved later. The memory will be stored in the database and can be retrieved by other tools or prompts.
         You can use this tool to store information that you want to remember for later use.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'title'    => ['required', 'string'],
            'contents' => ['required', 'string'],
            'preload'  => ['required', 'bool'],
        ]);

        $task = Memory::create([
            'title'    => $request->get('title'),
            'contents' => $request->get('contents'),
            'preload'  => $request->get('preload', false),
        ]);

        return Response::structured($task->toArray());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title'    => $schema->string()->description('Memory title')->required(),
            'contents' => $schema->string()->description('Memory contents')->required(),
            'preload'  => $schema->boolean()->description('Should be preloaded for every conversation')->default(false),
        ];
    }
}
