<?php

namespace App\Console\Commands;

use App\Channels\Telegram\Telegram;
use App\Jobs\ProcessIncomingMessageJob;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Console\Command;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;

class LarabotTelegramListenCommand extends Command
{
    protected $signature = 'larabot:telegram:listen {--timeout=60} {--daemon}';

    protected $description = 'Get telegram updates for the bot.';

    public function handle(Telegram $telegram)
    {
        $start = microtime(true);
        $offset = Setting::get('telegram_offset');
        $timeout = $this->option('timeout');
        $daemon = $this->option('daemon');
        $loop = true;

        if (! $daemon) {
            $this->line("Listening for Telegram updates for {$timeout} seconds...");
        } else {
            $this->line("Listening for Telegram updates in daemon mode... (Ctrl+C to stop)");
        }

        while ($loop) {
            try {
                $updates = $telegram->pollUpdates($timeout, $offset);

                /** @var Update $update */
                foreach ($updates as $update) {
                    $this->line("Received Telegram update: {$update->update_id}");

                    $perUpdateTelegram = new Telegram($update);
                    if (! $perUpdateTelegram->shouldProcessUpdate()) {
                        $offset = $update['update_id'];
                        continue;
                    }

                    $perUpdateTelegram->sendChatAction();

                    $telegram_message = $update->getMessage();
                    if (Message::where('uuid', $telegram_message->messageId)->exists()) {
                        \Log::warning('Received duplicate message, ignoring:', ['message_id' => $telegram_message->messageId]);
                        $offset = $update['update_id'];
                        continue;
                    }

                    $triggerMessage = null;
                    $chatId = $perUpdateTelegram->chat_id;

                    $file = null;
                    $file_type = null;
                    if (isset($telegram_message->photo)) {
                        $file = $telegram_message->photo->sortBy('file_size')->last();
                        $file_type = 'photo';
                    } elseif (isset($telegram_message->document)) {
                        $file = $telegram_message->document;
                        $file_type = 'document';
                    } elseif (isset($telegram_message->audio)) {
                        $file = $telegram_message->audio;
                        $file_type = 'audio';
                    } elseif (isset($telegram_message->video)) {
                        $file = $telegram_message->video;
                        $file_type = 'video';
                    } elseif (isset($telegram_message->voice)) {
                        $file = $telegram_message->voice;
                        $file_type = 'voice';
                    }

                    if ($file !== null) {
                        $downloaded_file = $perUpdateTelegram->downloadFile($file['file_id']);
                        \Log::info('Received a file from Telegram: ' . $downloaded_file, $perUpdateTelegram->getUpdate()->toArray());
                        $caption = $telegram_message->caption ?? null;
                        $fileMessage = Message::forFileReceived($downloaded_file, $file_type, $caption);
                        $fileMessage->channel_type = Message::CHANNEL_TELEGRAM;
                        $fileMessage->channel_conversation_id = $chatId;
                        $fileMessage->save();
                        $triggerMessage = $fileMessage;
                    }

                    if (isset($telegram_message->text) && $telegram_message->text !== '') {
                        $textMessage = Message::fromTelegramMessage($telegram_message);
                        $textMessage->save();
                        $triggerMessage = $textMessage;
                    }

                    if ($triggerMessage === null) {
                        $offset = $update['update_id'];
                        continue;
                    }

                    dispatch(new ProcessIncomingMessageJob($triggerMessage));
                    Setting::set('telegram_offset', $update['update_id']);
                    $offset = $update['update_id'];
                }
            } catch (TelegramSDKException $e) {
                $this->error('Error fetching updates: ' . $e->getMessage());
                continue;
            }

            if (! $daemon) {
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
