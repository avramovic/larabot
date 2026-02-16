<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Task;
use Illuminate\Console\Command;

class LarabotScheduleRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:schedule:run {id?} {--silent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled tasks on the LLM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($id = $this->argument('id')) {
            $task = Task::find($id);
            if (!$task) {
                $this->error("Task with ID {$id} not found.");
                return 1;
            }

            $this->line('Dispatching task #'.$task->id.' for execution.');
            dispatch(new ExecuteScheduledTaskJob($task));

            return 0;
        }

        if (!$this->option('silent')) {
            $this->line('Running scheduled LLM tasks...');
        }

        Task::where('repeat', 0)->update([
            'enabled' => false,
        ]);

        $tasks = Task::where('repeat', '!=', 0)
            ->where('enabled', true)
            ->get();

        /** @var Task $task */
        foreach ($tasks as $task) {
            if ($task->isDue()) {
                // Dispatch the task for execution
                $this->line('Dispatching task #'.$task->id.' for execution.');
                dispatch(new ExecuteScheduledTaskJob($task));
            }
        }

        return 0;
    }
}
