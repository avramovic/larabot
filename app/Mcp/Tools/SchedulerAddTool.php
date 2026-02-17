<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class SchedulerAddTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Add a scheduled task to the system scheduler. Schedule and prompt are required. Repeat is optional, default is -1 (infinite).
        Destination can be:
        - user: send the execution result to the user as a message
        - memory: save the execution result as a new (non-important) memory entry
        - auto: let the LLM decide the best way to handle the result after task execution, based on prompt contents and available tools
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'schedule'    => ['required', 'string'],
            'title'       => ['required', 'string'],
            'prompt'      => ['required', 'string'],
            'repeat'      => ['integer'],
            'destination' => ['string'],
        ]);

        $task = Task::create([
            'schedule'    => $request->get('schedule'),
            'title'       => $request->get('title'),
            'prompt'      => $request->get('prompt'),
            'repeat'      => $request->get('repeat', -1),
            'destination' => $request->get('destination', 'user'),
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
            'schedule'    => $schema->string()->description('REQUIRED. The cron expression defining the schedule, like: 0 8 * * *')->required(),
            'title'       => $schema->string()->description('REQUIRED. The task title')->required(),
            'prompt'      => $schema->string()->description('REQUIRED. The prompt to execute on the LLM model.')->required(),
            'destination' => $schema->string()->description('REQUIRED. Where to send task execution result: user/memory/auto')->required(),
            'repeat'      => $schema->integer()->description('How many times the task should repeat according to the schedule. -1 for infinite.')->default(-1),
        ];
    }
}
