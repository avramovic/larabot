<?php

namespace App\Channels\Telegram;

use App\Channels\BaseChannel;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

class Telegram extends BaseChannel
{
    protected Api $client;
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


    public function sendMessage(mixed $chat_id, string $message): Message
    {
        return $this->client->sendMessage([
            'chat_id' => $chat_id,
            'text'    => $message,
        ]);
    }

    /**
     * @throws TelegramSDKException
     */
    public function sendChatAction(mixed $chat_id, string $action): mixed
    {
        return $this->client->sendChatAction([
            'chat_id' => $chat_id,
            'action'  => $action,
        ]);
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

    public function getUpdate(?string $relation = null): Update|Collection|null
    {
        return $this->update ? ($relation ? $this->update->getMessage()->{$relation} : $this->update) : null;
    }
}
