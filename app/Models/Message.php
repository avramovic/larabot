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
        $template = file_get_contents(base_path('soul.md'));

        $intro = Blade::render($template, [
            'OS'              => PHP_OS_FAMILY === 'Darwin' ? 'macOS' : PHP_OS_FAMILY,
            'uname'           => php_uname('n'),
            'now'             => fn() => now(),
            'cwd'             => base_path(),
            'memories'        => Memory::where('preload', true)->get(),
            'bot_name'        => Setting::get('telegram_bot_name'),
            'user_first_name' => Setting::get('user_first_name'),
            'user_last_name'  => Setting::get('user_last_name'),
        ], true);

        return self::make([
            'role'     => 'system',
            'contents' => $intro,
        ]);
    }

    public static function systemToolExecutionMessage(Task $task): self
    {
        $task_prompt = $task->prompt;
        $prompt = <<<MARKDOWN
    This is a tool execution session. Here's what he user asked to do:
    "$task_prompt"

    When the tool execution finishes respond with the following JSON ONLY:

    {
        "should_notify": true,
        "message": "LLM response to be sent as a notification when the scheduled task runs."
    }

    - should_notify indicates whether the LLM response should be sent as a notification when the scheduled task runs. true/false
        For example if the scheduled task is to check for new unread emails every hour, the should_notify would be true to indicate
        that this message should be sent as a notification when the scheduled task runs, but only if there actually are new unread emails.
    - message is the LLM response to be sent as a notification when the scheduled task runs.
MARKDOWN;


        return self::make([
            'role'     => 'system',
            'contents' => $prompt,
        ]);
    }

}
