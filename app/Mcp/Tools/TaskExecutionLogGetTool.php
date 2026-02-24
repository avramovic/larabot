<?php

namespace App\Mcp\Tools;

use App\Models\TaskExecutionLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class TaskExecutionLogGetTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Get a single task execution log by ID. Returns full record including output_text and tool_calls. Use task-execution-log-list to find log IDs.
    MARKDOWN;

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:task_execution_logs,id'],
        ]);

        $log = TaskExecutionLog::with('task')->find($request->get('id'));
        if (! $log) {
            return Response::error('Task execution log not found. Use task-execution-log-list to get valid IDs.');
        }

        return Response::structured([
            'id'          => $log->id,
            'task_id'     => $log->task_id,
            'task_title'  => $log->task?->title ?? null,
            'output_text' => $log->output_text,
            'tool_calls'  => $log->tool_calls,
            'status'      => $log->status,
            'created_at'  => $log->created_at->toIso8601String(),
            'updated_at'  => $log->updated_at->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('REQUIRED. ID of the task execution log.')->required(),
        ];
    }
}
