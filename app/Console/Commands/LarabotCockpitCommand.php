<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LarabotCockpitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:cockpit {--solo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Larabot cockpit - a simple interface to monitor and manage Larabot processes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        var_dump($this->option('solo'));
        $this->alert('Welcome to Larabot');
    }
}
