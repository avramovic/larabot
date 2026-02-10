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
        $what = $this->choice('What do you want to clear?', ['Nothing', 'Context', 'Memories', 'Settings', 'Everything'], 'Nothing');

        switch ($what) {
            case 'Context':
                \DB::table('messages')->truncate();
                $this->info('Context cleared successfully.');
                break;
            case 'Memories':
                \DB::table('memories')->truncate();
                $this->info('Memories cleared successfully.');
                break;
            case 'Settings':
                \DB::table('settings')->truncate();
                $this->info('Settings cleared successfully.');
                break;
            case 'Everything':
                \DB::table('messages')->truncate();
                \DB::table('memories')->truncate();
                \DB::table('settings')->truncate();
                $this->info('All context, memories and settings cleared successfully.');
                break;
            default:
                $this->info('Nothing was cleared.');
        }
    }
}
