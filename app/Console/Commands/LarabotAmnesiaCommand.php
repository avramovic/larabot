<?php

namespace App\Console\Commands;

use App\Models\Memory;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Console\Command;

class LarabotAmnesiaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:amnesia';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear bot context, memories and settings.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->confirm('Are you sure you want to clear all the context, memories and settings?')) {
            Message::delete();
            Memory::delete();
            Setting::delete();
            $this->info('Amnesia applied successfully.');
        }
    }
}
