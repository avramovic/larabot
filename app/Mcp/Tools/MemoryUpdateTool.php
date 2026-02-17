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
            'id'        => ['required', 'integer', 'exists:memories,id'],
            'title'     => ['string'],
            'contents'  => ['string'],
            'important' => ['boolean'],
        ]);


        /** @var Memory $memory */
        $memory = Memory::find($request->get('id'));
        if (!$memory) {
            return Response::error('Task not found. Try listing all tasks to get the correct ID.');
        }

        $memory->update([
            'title'     => $request->get('title', $memory->title),
            'contents'  => $request->get('contents', $memory->contents),
            'important' => $request->get('important', $memory->important),
        ]);

        return Response::text(sprintf('Memory %d updated successfully.', $memory->id));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title'     => $schema->string()->description('REQUIRED. Memory title')->required(),
            'contents'  => $schema->string()->description('REQUIRED. Memory contents')->required(),
            'important' => $schema->boolean()->description('Should this memory be preloaded for every conversation')->default(false),
        ];
    }
}
