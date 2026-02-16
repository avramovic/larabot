<?php

namespace App\Jobs;

use App\Channels\Telegram\Telegram;
use App\Models\Message;
use App\Models\Setting;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Soukicz\Llm\LLMConversation;
use Telegram\Bot\Objects\Message as TelegramMessage;
use Telegram\Bot\Objects\Update;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 1200;

    protected ?Telegram $telegram = null;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Update $update)
    {
        $this->telegram = new Telegram($this->update);
    }

    /**
     * Execute the job.
     */
    public function handle(LLMChatService $chatService): void
    {

        $bot_info = $this->telegram->getBotInfo();
        Setting::updateOrCreate(
            ['key' => 'bot_name'],
            ['value' => $bot_info->first_name],
        );

        \Log::info('Processing Telegram update: ', $this->update->toArray());

        //safety check to ensure we only process messages from the configured chat and owner
        $telegram_user = $this->telegram->getUpdate('from');
        $telegram_chat = $this->telegram->getUpdate('chat');

        if (empty($this->telegram->owner_id)) {
            Setting::set('telegram_owner_id', $telegram_user->id);
            Setting::set('user_first_name', $telegram_user->first_name);
            Setting::set('user_last_name', $telegram_user->last_name);
            $this->telegram->owner_id = $telegram_user->id;

            if (empty($this->telegram->chat_id)) {
                Setting::set('telegram_chat_id', $telegram_chat->id);
                $this->telegram->chat_id = $telegram_chat->id;
            }

            $this->telegram->sendMessage("👋 Hi {$telegram_user->first_name}! You've been set as the owner of this Telegram bot. You can now start sending messages to the bot and it will respond using the LLM.");

            return;
        }


        if ($telegram_user->id != $this->telegram->owner_id) {
            \Log::warning("Received message from unauthorized source:", $this->telegram->getUpdate()->toArray());
            $owner_name = Setting::get('user_first_name', '[redacted]');
            $this->telegram->client->sendMessage([
                'chat_id' => $telegram_user->id,
                'text'    => "✋ Hi {$telegram_user->first_name}! I am currently configured to only respond to my owner ({$owner_name}). If you think this is a mistake, please contact the owner of this bot. Or get your own at https://github.com/avramovic/larabot"
            ]);

            return;
        }

        /** @var TelegramMessage $telegram_message */
        $telegram_message = $this->update->getMessage();
        if (Message::where('uuid', $telegram_message->messageId)->exists()) {
            \Log::warning("Received duplicate message, ignoring:", ['message_id' => $telegram_message->messageId]);

            return;
        }

        $conversation = $chatService->getConversation(config('llm.sliding_window', -1));
        $conversation = $this->handleMediaUpload($conversation, $telegram_message);

        if (isset($telegram_message->text)) {
            $message = Message::fromTelegramMessage($telegram_message);
            $message->save();
            $conversation = $conversation->withMessage($message->toLLMMessage());
        }

        try {
            $response = $chatService->send($conversation);
        } catch (\Exception $e) {
            \Log::error($e->getMessage(), $e->getTrace());
            $this->telegram->sendMessage("❌ LLM request failed: " . $e->getMessage());

            return;
        }

        Setting::set('telegram_offset', $this->update['update_id']);

        if ($message_text = $response->getLastText()) {
            $message_text = $this->postProcessMessage($message_text);
            $response_message = Message::fromAssistant($message_text);
            $response_message->save();
            $this->telegram->sendMessage($message_text);
        } else {
            $this->telegram->sendMessage('❌ LLM returned empty response. Stop reason: ' . $response->getStopReason()->value . '; try rephrasing your prompt.');
        }
    }

    protected function handleMediaUpload(LLMConversation $conversation, TelegramMessage $telegram_message): LLMConversation
    {
        $file = null;
        $file_type = null;

        if (isset($telegram_message->photo)) {
            $file = $telegram_message->photo->sortBy('file_size')->last();
            $file_type = 'photo';
        }

        if (isset($telegram_message->document)) {
            $file = $telegram_message->document;
            $file_type = 'document';
        }

        if (isset($telegram_message->audio)) {
            $file = $telegram_message->audio;
            $file_type = 'audio';
        }

        if (isset($telegram_message->video)) {
            $file = $telegram_message->video;
            $file_type = 'video';
        }

        if (isset($telegram_message->voice)) {
            $file = $telegram_message->voice;
            $file_type = 'voice';
        }

        if (!is_null($file)) {
            $downloaded_file = $this->telegram->downloadFile($file['file_id']);
            \Log::info("Received a file from Telegram: " . $downloaded_file, $this->telegram->getUpdate()->toArray());
            $file_message = Message::systemFileReceivedMessage($downloaded_file, $file_type, $telegram_message);
            $file_message->save();
            $conversation = $conversation->withMessage($file_message->toLLMMessage());
        }

        return $conversation;
    }

    protected function postProcessMessage(string $text): string
    {
        // add more post-processing steps here if needed
        return $this->convertMarkdownTables($text);
    }

    private function convertMarkdownTables(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inTable = false;
        $headers = [];
        $rows = [];

        foreach ($lines as $line) {
            $isTableLine = str_starts_with(trim($line), '|');
            $isSeparator = preg_match('/^\|[\s\-\|]+\|$/', trim($line));

            if ($isTableLine && !$isSeparator) {
                $inTable = true;
                $cells = array_map('trim', explode('|', trim($line, '|')));

                if (empty($headers)) {
                    $headers = $cells;
                } else {
                    $rows[] = $cells;
                }
            } elseif (!$isTableLine && $inTable) {
                // Kraj tabele - konvertuj
                $result[] = $this->formatTable($headers, $rows);
                $headers = [];
                $rows = [];
                $inTable = false;
                $result[] = $line;
            } elseif (!$isSeparator) {
                $result[] = $line;
            }
        }

        if ($inTable && !empty($headers)) {
            $result[] = $this->formatTable($headers, $rows);
        }

        return implode("\n", $result);
    }

    private function formatTable(array $headers, array $rows): string
    {
        $output = [];

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $i => $header) {
                $value = $row[$i] ?? '-';
                $line[] = "*{$header}*: {$value}";
            }
            $output[] = implode(', ', $line);
        }

        return implode("\n", $output);
    }

    public function failed(\Throwable $exception): void
    {
        // Notify the user via Telegram that an error occurred, but don't expose sensitive details
        try {
            $this->telegram->sendMessage('❌ ' . $exception->getMessage());
        } catch (\Exception $e) {
            \Log::error("Failed to send error message (" . $exception->getMessage() . ") to Telegram user because of: " . $e->getMessage(),
                $e->getTrace());
        }
    }

}
