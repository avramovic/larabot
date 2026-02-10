<?php

namespace App\Channels;

i'use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Objects\Message;

interface ChatInterface
{
    public function sendMessage(string $message): Message;
    public function sendPhoto(string|InputFile $photo): Message;
    public function sendVideo(string|InputFile $video): Message;
    public function sendAudio(string|InputFile $audio): Message;
    public function sendFile(string|InputFile $document): Message;
    public function sendChatAction(string $action = 'typing'): bool;
}
