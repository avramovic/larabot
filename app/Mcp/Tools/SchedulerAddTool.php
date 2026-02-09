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
        Add a scheduled task to the system scheduler. Respond with the following JSON:

        ```json
        {
            "should_notify": true,
            "message": "LLM response to be sent as a notification when the scheduled task runs."
        }
        ```

        - should_notify indicates whether the LLM response should be sent as a notification when the scheduled task runs. true/false
            For example if the scheduled task is to check for new unread emails every hour, the should_notify would be true to indicate
            that this message should be sent as a notification when the scheduled task runs, but only if there actually are new unread emails.
        - message is the LLM response to be sent as a notification when the scheduled task runs.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'schedule' => ['required', 'string'],
            'prompt' => ['required', 'string'],
            'repeat' => ['integer'],
        ]);

        $task = Task::create([
            'schedule' => $request->get('schedule'),
            'prompt'   => $request->get('prompt'),
            'repeat'   => $request->get('repeat', -1),
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
            'schedule' => $schema->string()->description('The cron expression defining the schedule.')->required(),
            'prompt'   => $schema->string()->description('The prompt to execute on the LLM model.')->required(),
            'repeat'   => $schema->integer()->description('How many times the task should repeat according to the schedule. -1 for infinite.')->default(-1),
        ];
    }
}
