<?php

namespace App\Channels;

use App\Channels\Telegram\Telegram;
use App\Models\Message;
use App\Models\Setting;

class ChannelResolver
{
    public function resolveForMessage(Message $message): ChatInterface
    {
        $channelType = $message->channel_type ?? Message::CHANNEL_TELEGRAM;
        $conversationId = $message->channel_conversation_id ?? Setting::get('telegram_chat_id');

        return match ($channelType) {
            Message::CHANNEL_TELEGRAM => new Telegram(null, $conversationId),
            default => new Telegram(null, $conversationId),
        };
    }
}
