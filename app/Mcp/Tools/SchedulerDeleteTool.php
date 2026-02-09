<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class SchedulerDeleteTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Delete a scheduled task in the system scheduler.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:tasks,id'],
        ]);

        $task = Task::find($request->get('id'));
        if (!$task) {
            return Response::error('Task not found. Try listing all tasks to get the correct ID.');
        }

        $taskData = $task->toArray();
        $task->delete();

        return Response::structured($taskData);
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
