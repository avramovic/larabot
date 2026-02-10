<?php

namespace App\Console\Commands;

use App\Channels\Telegram\Telegram;
use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\Setting;
use Illuminate\Console\Command;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;

class TelegramListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:listen {--timeout=60} {--daemon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get telegram updates for the bot. This command is intended to be used with a cron job to periodically fetch updates from Telegram and process them.';

    /**
     * Execute the console command.
     */
    public function handle(Telegram $telegram)
    {
        $start = microtime(true);
        $offset = Setting::get('telegram_offset');
        $timeout = $this->option('timeout');
        $daemon = $this->option('daemon');
        $loop = true;

        if (!$daemon) {
            $this->line("Listening for Telegram updates for {$timeout} seconds...");
        } else {
            $this->line("Listening for Telegram updates in daemon mode...");
        }

        while ($loop) {
            try {
                $updates = $telegram->pollUpdates($timeout, $offset);

                /** @var Update $update */
                foreach ($updates as $update) {
                    $this->line("Received Telegram update: {$update->update_id}");
                    $telegram->sendChatAction();
                    dispatch(new ProcessTelegramUpdateJob($update));
                    $offset = $update['update_id'];
                }
            } catch (TelegramSDKException $e) {
                $this->error($e->getMessage());
                continue;
            }

            if (!$daemon) {
                $execution_time = microtime(true) - $start;
                $time_to_next_cron = floor(max(0, $timeout - $execution_time));
                $loop = false;

                if ($time_to_next_cron > 1) {
                    $this->line("Listening for Telegram updates for additional {$time_to_next_cron} seconds...");
                    $timeout = floor($time_to_next_cron);
                    $loop = true;
                }
            }
        }

        $this->line(sprintf("Exited after %d seconds.", microtime(true) - $start));
    }
}
