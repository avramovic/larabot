<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class LarabotForgetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:forget';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear bot context by deleting all messages.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->note('This command will clear all the context by deleting all messages. Memories and settings will be preserved.');
        $this->pause('test');
        $this->alert('text');
        if ($this->confirm('Are you sure you want to clear all the context?')) {
            Message::delete();
            $this->info('Context cleared successfully.');
        }
    }
}
