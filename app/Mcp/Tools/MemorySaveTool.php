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
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

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

    protected function checkTruthiness(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);
            return in_array($lowerValue, ['true', '1', 'yes'], true);
        }

        if (is_numeric($value)) {
            return $value == 1;
        }

        return false;
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
            'preload'  => $schema->string()->description('Should be preloaded for every conversation (true/false)')->required(),
        ];
    }
}
