<?php

namespace App\Mcp\Tools;

use App\Models\TaskExecutionLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class TaskExecutionLogDeleteTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Delete a task execution log by ID. Use task-execution-log-list to find IDs. Cannot create or modify logs—only delete.
    MARKDOWN;

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:task_execution_logs,id'],
        ]);

        $log = TaskExecutionLog::find($request->get('id'));
        if (! $log) {
            return Response::error('Task execution log not found. Use task-execution-log-list to get valid IDs.');
        }

        $id = $log->id;
        $log->delete();

        return Response::text(sprintf('Task execution log %d deleted successfully.', $id));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('REQUIRED. ID of the task execution log to delete.')->required(),
        ];
    }
}
