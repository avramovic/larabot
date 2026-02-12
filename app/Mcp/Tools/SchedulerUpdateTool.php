<?php

namespace App\Mcp\Tools;

use App\Mcp\BaseMcpTool;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class SchedulerUpdateTool extends BaseMcpTool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Update a scheduled task in the system scheduler.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'id'          => ['required', 'integer', 'exists:tasks,id'],
            'schedule'    => ['string'],
            'prompt'      => ['string'],
            'repeat'      => ['integer'],
            'destination' => ['string'],
            'enabled'     => ['string'],
        ]);

        $task = Task::find($request->get('id'));
        if (!$task) {
            return Response::error('Task not found. Try listing all tasks to get the correct ID.');
        }

        $task->update([
            'schedule'    => $request->get('schedule', $task->schedule),
            'prompt'      => $request->get('prompt', $task->prompt),
            'repeat'      => $request->get('repeat', $task->repeat),
            'destination' => $request->get('destination', $task->destination),
            'enabled'     => $this->checkTruthiness($request->get('enabled', $task->enabled)),
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
            'id'          => $schema->integer()->description('REQUIRED. ID of the task')->required(),
            'schedule'    => $schema->string()->description('The cron expression defining the schedule.'),
            'prompt'      => $schema->string()->description('The prompt to execute on the LLM model.'),
            'repeat'      => $schema->integer()->description('How many times the task should repeat according to the schedule. -1 for infinite.'),
            'destination' => $schema->string()->description('Where to send task execution result: user/memory/auto')->default('user'),
            'enabled'     => $schema->string()->description('Whether the task is enabled or not. true/false'),
        ];
    }
}
