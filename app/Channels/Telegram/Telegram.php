<?php

namespace App\Channels\Telegram;

use App\Channels\ChatInterface;
use App\Channels\Telegram\Traits\TelegramMessagePreProcessorHelper;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\User;

class Telegram implements ChatInterface
{
    use TelegramMessagePreProcessorHelper;

    public Api $client;
    public ?string $chat_id;
    public ?string $owner_id;
    protected ?Update $update;

    public function __construct(?Update $update = null, ?string $chatId = null)
    {
        $api_key = config('channels.channels.telegram.bot_token');
        if (empty($api_key)) {
            throw new \InvalidArgumentException("Telegram API key is not configured");
        }

        $this->client = new Api($api_key);
        $this->owner_id = Setting::get('telegram_owner_id');
        $this->update = $update;

        if ($chatId !== null) {
            $this->chat_id = $chatId;
        } elseif ($update && $update->getMessage()?->getChat()) {
            $this->chat_id = (string) $update->getMessage()->getChat()->getId();
        } else {
            $this->chat_id = Setting::get('telegram_chat_id');
        }
    }


    /**
     * @throws TelegramSDKException
     */
    public function sendMessage(string $message): Message
    {
        $message = $this->preProcessMessage($message);

        return $this->client->sendMessage([
            'chat_id'    => $this->chat_id,
            'text'       => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    public function sendChatAction(string $action = 'typing'): bool
    {
        try {
            return $this->client->sendChatAction([
                'chat_id' => $this->chat_id,
                'action'  => $action,
            ]);
        } catch (TelegramSDKException $e) {
            \Log::error("Failed to send chat action to Telegram: " . $e->getMessage(), ['trace' => $e->getTrace()]);

            return false;
        }
    }

    /**
     * @throws TelegramSDKException
     */
    public function pollUpdates(int $timeout = 60, mixed $last_offset = null): array
    {
        return $this->client->getUpdates([
            'timeout' => $timeout,
            'offset'  => $last_offset ? $last_offset + 1 : null,
        ]);
    }


    public function getBotInfo(): User
    {
        return $this->client->getMe();
    }

    public function getUpdate(?string $relation = null): Update|Collection|null
    {
        return $this->update ? ($relation ? $this->update->getMessage()->{$relation} : $this->update) : null;
    }

    public function sendPhoto(string|InputFile $photo): Message
    {
        return $this->client->sendPhoto([
            'chat_id' => $this->chat_id,
            'photo'   => $photo instanceof InputFile ? $photo : InputFile::create($photo),
            'caption' => $this->generateFileCaption($photo),
        ]);
    }

    public function sendVideo(string|InputFile $video): Message
    {
        return $this->client->sendVideo([
            'chat_id' => $this->chat_id,
            'video'   => $video instanceof InputFile ? $video : InputFile::create($video),
            'caption' => $this->generateFileCaption($video),
        ]);
    }

    public function sendAudio(string|InputFile $audio): Message
    {
        return $this->client->sendAudio([
            'chat_id' => $this->chat_id,
            'audio'   => $audio instanceof InputFile ? $audio : InputFile::create($audio),
            'caption' => $this->generateFileCaption($audio),
        ]);
    }

    public function sendFile(string|InputFile $document): Message
    {
        return $this->client->sendDocument([
            'chat_id'  => $this->chat_id,
            'document' => $document instanceof InputFile ? $document : InputFile::create($document),
            'caption'  => $this->generateFileCaption($document),
        ]);
    }

    protected function generateFileCaption(string|InputFile $file): string
    {
        return $file instanceof InputFile ? $file->getFilename() : basename($file);
    }

    public function downloadFile(string $file_id): string
    {
        return $this->client->downloadFile($file_id, sys_get_temp_dir() . '/' . Str::uuid());
    }

    /**
     * Returns false if the update was handled (first-time setup or unauthorized), true if the listener should process and dispatch.
     */
    public function shouldProcessUpdate(): bool
    {
        $bot_info = $this->getBotInfo();
        Setting::updateOrCreate(
            ['key' => 'bot_name'],
            ['value' => $bot_info->first_name],
        );

        $telegram_user = $this->getUpdate('from');
        $telegram_chat = $this->getUpdate('chat');

        if (empty($this->owner_id)) {
            Setting::set('telegram_owner_id', $telegram_user->id);
            Setting::set('user_first_name', $telegram_user->first_name);
            Setting::set('user_last_name', $telegram_user->last_name);
            $this->owner_id = (string) $telegram_user->id;

            if (empty($this->chat_id)) {
                Setting::set('telegram_chat_id', $telegram_chat->id);
                $this->chat_id = (string) $telegram_chat->id;
            }

            $this->sendMessage("👋 Hi {$telegram_user->first_name}! You've been set as the owner of this Telegram bot. You can now start sending messages to the bot and it will respond using the LLM.");

            return false;
        }

        if ($telegram_user->id != $this->owner_id) {
            \Log::warning("Received message from unauthorized source:", $this->getUpdate()->toArray());
            $owner_name = Setting::get('user_first_name', '[redacted]');
            $this->client->sendMessage([
                'chat_id' => $telegram_user->id,
                'text'    => "✋ Hi {$telegram_user->first_name}! I am currently configured to only respond to my owner ({$owner_name}). If you think this is a mistake, please contact the owner of this bot. Or get your own at https://github.com/avramovic/larabot",
            ]);

            return false;
        }

        return true;
    }
}
