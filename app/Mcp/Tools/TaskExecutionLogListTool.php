<?php

namespace App\Mcp\Tools;

use App\Models\TaskExecutionLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class TaskExecutionLogListTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        List task execution logs. Use to review when and how scheduled tasks ran. Optional filters: task_id, status. Returns id, task info, output excerpt, status, created_at. Use task-execution-log-get to fetch full details including tool calls.
    MARKDOWN;

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'status'  => ['nullable', 'string', 'in:success,failed'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TaskExecutionLog::query()->with('task')->orderByDesc('created_at');
        if ($request->has('task_id')) {
            $query->where('task_id', $request->get('task_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        $limit = (int) $request->get('limit', 50);
        $logs  = $query->limit($limit)->get();

        $items = $logs->map(fn (TaskExecutionLog $log): array => [
            'id'              => $log->id,
            'task_id'         => $log->task_id,
            'task_title'      => $log->task?->title ?? '[unknown]',
            'output_excerpt'  => $log->output_text ? mb_substr($log->output_text, 0, 200) . (mb_strlen($log->output_text) > 200 ? '…' : '') : null,
            'status'          => $log->status,
            'created_at'      => $log->created_at->toIso8601String(),
        ])->all();

        return Response::structured([
            'logs'  => $items,
            'count' => count($items),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('Optional. Filter by task ID.')->nullable(),
            'status'  => $schema->string()->description('Optional. Filter by status: success or failed.')->nullable(),
            'limit'   => $schema->integer()->description('Optional. Max number of logs to return (default 50, max 100).')->nullable(),
        ];
    }
}
