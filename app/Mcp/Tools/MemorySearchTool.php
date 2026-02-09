<?php

namespace App\Mcp\Tools;

use App\Models\Memory;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class MemorySearchTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search memories by comma separated list of keywords.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        $request->validate([
            'keywords' => ['required', 'string'],
        ]);

        $keywords = explode(',', $request->get('keywords'));
        $memories = Memory::where('preload', false)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('contents', 'like', '%' . trim($keyword) . '%')
                        ->orWhere('title', 'like', '%' . trim($keyword) . '%');
                }
            });

        $memories = $memories->get();

        return Response::structured(['memories' => $memories->toArray()]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keywords' => $schema->string()->description('A comma separated list of keywords')->required(),
        ];
    }
}
