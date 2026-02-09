<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\Message\LLMMessage;
use Telegram\Bot\Objects\Message as TelegramMessage;

class Message extends Model
{
    protected $guarded = [];


    public static function fromTelegramMessage(TelegramMessage|Collection $message, string $role = 'user'): self
    {
        return self::make([
            'role'       => $role,
            'contents'   => $message->text,
            'uuid'       => $message->messageId,
            'created_at' => now(),
        ]);
    }

    public static function fromLLMMessage(LLMMessage $message): self
    {
        $messages = $message->getContents()->getMessages();

        return self::make([
            'role'     => $message->isUser() ? 'user' : ($message->isAssistant() ? 'assistant' : 'system'),
            'contents' => $messages[count($messages) - 1]?->getText() ?? '',
            'uuid'     => Str::uuid(),
        ]);
    }

    public function toLLMMessage(): LLMMessage
    {
        return match ($this->role) {
            'user' => LLMMessage::createFromUserString($this->contents),
            'assistant' => LLMMessage::createFromAssistantString($this->contents),
            'system' => LLMMessage::createFromSystemString($this->contents),
            default => throw new \InvalidArgumentException("Invalid message type: {$this->type}"),
        };
    }

    public static function systemIntroductoryMessage(): self
    {
        return self::make([
            'role'     => 'system',
            'contents' => 'You are Larabot, a helpful assistant that runs on the user\'s computer and has access to execute commands, search web and make http requests. Your task is to help user manage their computer, which is a "' . php_uname() . '". Always reply in the language the user used to ask the question.'
        ]);

        $intro = Blade::compileString(file_get_contents(resource_path('views/intro.md.php')));

        return new self([
            'role'     => 'system',
            'contents' => $intro,
        ]);
    }

}
