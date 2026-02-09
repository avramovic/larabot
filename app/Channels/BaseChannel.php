<?php

namespace App\Channels;

use Soukicz\Llm\Message\LLMMessage;

abstract class BaseChannel
{
    abstract public function sendMessage(mixed $chat_id, string $message);

    abstract public function sendChatAction(mixed $chat_id, string $action): mixed;
}
