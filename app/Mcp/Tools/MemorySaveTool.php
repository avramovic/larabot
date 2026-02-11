<?php

namespace App\Mcp\Tools;

use App\Mcp\BaseMcpTool;
use App\Models\Memory;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class MemorySaveTool extends BaseMcpTool
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
            'preload'  => ['string'],
        ]);

        $task = Memory::create([
            'title'    => $request->get('title'),
            'contents' => $request->get('contents'),
            'preload'  => $this->checkTruthiness($request->get('preload', false)),
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
            'title'    => $schema->string()->description('REQUIRED. Memory title')->required(),
            'contents' => $schema->string()->description('REQUIRED. Memory contents')->required(),
            'preload'  => $schema->string()->description('REQUIRED. Should be preloaded for every conversation (true/false)')->required(),
        ];
    }
}
