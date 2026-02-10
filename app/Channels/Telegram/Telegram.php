<?php

namespace App\Channels\Telegram;

use App\Channels\ChatInterface;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\User;

class Telegram implements ChatInterface
{
    public Api $client;
    public ?string $chat_id;
    public ?string $owner_id;

    protected ?Update $update;

    public function __construct(?Update $update = null)
    {
        $api_key = config('channels.channels.telegram.bot_token');
        if (empty($api_key)) {
            throw new \InvalidArgumentException("Telegram API key is not configured");
        }

        $this->client = new Api($api_key);
        $this->chat_id = Setting::get('telegram_chat_id');
        $this->owner_id = Setting::get('telegram_owner_id');
        $this->update = $update;
    }


    /**
     * @throws TelegramSDKException
     */
    public function sendMessage(string $message): Message
    {
        return $this->client->sendMessage([
            'chat_id' => $this->chat_id,
            'text'    => $message,
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
}
