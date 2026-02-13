<?php

namespace App\Mcp\Tools;

use App\Mcp\BaseMcpTool;
use App\Models\Memory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class MemorySaveTool extends BaseMcpTool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Add a memory which can be retrieved later. The memory will be stored in the database and can be retrieved by other tools.
        Memories longer than 1000 characters will not be preloaded in the conversation context by default, but can still be retrieved when needed.
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

        $contents = $request->get('contents');
        $preload = $this->checkTruthiness($request->get('preload', false));
        $title = Str::limit($request->get('title'), 255);

        if (strlen($contents) > 1000) {
            $preload = false;
        }

        $task = Memory::create([
            'title'    => $title,
            'contents' => $contents,
            'preload'  => $preload,
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
            'title'    => $schema->string()->description('REQUIRED. Memory title. Max 255 characters')->required(),
            'contents' => $schema->string()->description('REQUIRED. Memory contents. If length is over 1000 characters it can not be save as preloaded')->required(),
            'preload'  => $schema->string()->description('REQUIRED. Should be preloaded for every conversation (true/false)')->required(),
        ];
    }
}
