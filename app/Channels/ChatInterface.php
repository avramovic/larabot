<?php

namespace App\Channels;

use Telegram\Bot\Objects\Message;

interface ChatInterface
{
    public function sendMessage(string $message): Message;

    public function sendChatAction(string $action = 'typing'): bool;
}
