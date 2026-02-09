<?php

namespace App\Mcp\Tools;

use App\Models\Memory;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class MemoryUpdateTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Update a memory by it's ID.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'id'       => ['required', 'integer', 'exists:memories,id'],
            'title'    => ['string'],
            'contents' => ['string'],
            'preload'  => ['boolean'],
        ]);


        $memory = Memory::find($request->get('id'));
        if (!$memory) {
            return Response::error('Task not found. Try listing all tasks to get the correct ID.');
        }

        $memory->update([
            'title'    => $request->get('title', $memory->title),
            'contents' => $request->get('contents', $memory->contents),
            'preload'  => $request->get('preload', $memory->preload),
        ]);

        return Response::structured($memory->toArray());
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
            'preload'  => $schema->boolean()->description('Should this memory be preloaded for every conversation')->default(false),
        ];
    }
}
