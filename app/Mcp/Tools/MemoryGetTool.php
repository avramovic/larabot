<?php

namespace App\Mcp\Tools;

use App\Models\Memory;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class MemoryGetTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get a memory by its ID.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        $request->validate([
            'id' => ['required', 'integer', 'exists:memories,id'],
        ]);

        $id = (int) $request->get('id');
        $memory = Memory::find($id);

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
            'id' => $schema->integer()->description('REQUIRED. ID of memory to retrieve')->required(),
        ];
    }
}
