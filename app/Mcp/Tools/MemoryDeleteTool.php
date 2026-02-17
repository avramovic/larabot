<?php

namespace App\Mcp\Tools;

use App\Models\Memory;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class MemoryDeleteTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Delete a memory.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:memories,id'],
        ]);

        $memory = Memory::find($request->get('id'));
        if (!$memory) {
            return Response::error('Memory not found. Try searching memories get the correct ID.');
        }

        $memory->delete();

        return Response::text(sprintf('Memory %d deleted successfully.', $memory->id));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id'   => $schema->integer()->description('ID of the task to delete')->required(),
        ];
    }
}
