<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LarabotCronCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute scheduled tasks and other periodic maintenance jobs for Larabot. This command is intended to be run every minute by a cron job to ensure timely execution of scheduled tasks and regular maintenance.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('Running Larabot cron jobs... (Ctrl+C to stop)');

        while (true) {
            $this->call('larabot:schedule:run', ['--silent' => true]);
            sleep(60);
        }
    }
}
