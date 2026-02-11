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
    protected $signature = 'larabot:schedule:run {id?} {--force}';

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
        $this->info('Running scheduled LLM tasks...');

        if ($id = $this->argument('id')) {
            $task = Task::find($id);
            if (!$task) {
                $this->error("Task with ID {$id} not found.");
                return;
            }
            if (!$task->isDue() && !$this->option('force')) {
                $this->error("Task #{$id} is not due for execution yet. Use --force to execute it anyway.");
                return;
            }
            $this->line('Dispatching task #'.$task->id.' for execution.');
            dispatch(new ExecuteScheduledTaskJob($task));
            return;
        }

        Task::where('repeat', 0)->delete();

        $tasks = Task::where('repeat', '!=', 0)->get();
        /** @var Task $task */
        foreach ($tasks as $task) {
            if ($task->isDue()) {
                // Dispatch the task for execution
                $this->line('Dispatching task #'.$task->id.' for execution.');
                dispatch(new ExecuteScheduledTaskJob($task));
            }
        }

        $this->info('Scheduled tasks have been dispatched for execution.');
    }
}
