<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteScheduledTaskJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Task $task)
    {


        $task->decrement('repeat');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

    }
}
