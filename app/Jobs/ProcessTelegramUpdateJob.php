<?php

namespace App\Jobs;

use App\Channels\Telegram\Telegram;
use App\Models\Message;
use App\Models\Setting;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Telegram\Bot\Objects\Update;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    protected ?Telegram $telegram = null;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Update $update)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(LLMChatService $chatService): void
    {
        $telegram = new Telegram($this->update);

        $bot_info = $telegram->getBotInfo();
        Setting::updateOrCreate(
            ['key' => 'bot_name'],
            ['value' => $bot_info->first_name],
        );

        \Log::info('Processing Telegram update: ', $this->update->toArray());

        //safety check to ensure we only process messages from the configured chat and owner
        $telegram_user = $telegram->getUpdate('from');
        $telegram_chat = $telegram->getUpdate('chat');

        if (empty($telegram->owner_id)) {
            Setting::set('telegram_owner_id', $telegram_user->id);
            Setting::set('user_first_name', $telegram_user->first_name);
            Setting::set('user_last_name', $telegram_user->last_name);
            $telegram->owner_id = $telegram_user->id;

            if (empty($telegram->chat_id)) {
                Setting::set('telegram_chat_id', $telegram_chat->id);
                $telegram->chat_id = $telegram_chat->id;
            }

            $telegram->sendMessage("👋 Hi {$telegram_user->first_name}! You've been set as the owner of this Telegram bot. You can now start sending messages to the bot and it will respond using the LLM.");

            return;
        }


        if ($telegram_user->id != $telegram->owner_id) {
            \Log::warning("Received message from unauthorized source:", $telegram->getUpdate()->toArray());
            $owner_name = Setting::get('user_first_name', '[redacted]');
            $telegram->client->sendMessage([
                'chat_id' => $telegram_user->id,
                'message' => "✋ Hi {$telegram_user->first_name}! I am currently configured to only respond to my owner ({$owner_name}). If you think this is a mistake, please contact the owner of this bot. Or get your own at https://github.com/avramovic/larabot"
            ]);

            return;
        }

        $telegram_message = $this->update->getMessage();
        if (Message::where('uuid', $telegram_message->messageId)->exists()) {
            \Log::warning("Received duplicate message, ignoring:", ['message_id' => $telegram_message->messageId]);

            return;
        }

        $conversation = $chatService->getConversation(config('llm.sliding_window', -1));

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
            $downloaded_file = $telegram->downloadFile($file['file_id']);
            \Log::warning("Received photo from telegram: ".$downloaded_file, $telegram->getUpdate()->toArray());
            $file_message = Message::systemFileReceivedMessage($downloaded_file, $file_type);
            $file_message->save();
            $conversation = $conversation->withMessage($file_message->toLLMMessage());
        }

        if (isset($telegram_message->text)) {
            $message = Message::fromTelegramMessage($telegram_message);
            $conversation = $conversation->withMessage($message->toLLMMessage());
        }

        try {
            $response = $chatService->send($conversation);
        } catch (\Exception $e) {
            \Log::error($e->getMessage(), $e->getTrace());
            $telegram->sendMessage("❌ Sorry, something went wrong while processing your request: " . $e->getMessage());

            return;
        }

        if (isset($message)) {
            $message->save();
        }

        Setting::set('telegram_offset', $this->update['update_id']);

        if (!empty($response->getLastText())) {
            $response_message = Message::fromLLMMessage($response->getConversation()->getLastMessage());
            $response_message->save();
            $telegram->sendMessage($response->getLastText());
        } else {
            $telegram->sendMessage('❌ Sorry, I was not able to generate a response to your message. Stop reason: '.$response->getStopReason()->value.'; response:'.$response->getLastText());
        }
    }

}
