<?php

namespace App\Console\Commands;

class LarabotDashboardCommand extends BaseLarabotCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:dashboard {--solo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Larabot dashboard - a simple interface to monitor and manage Larabot processes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        var_dump($this->option('solo'));
        $this->alert('Welcome to Larabot');



        while ($selected = $this->choice('What do you want to check?', [])) {

        }
    }
}
