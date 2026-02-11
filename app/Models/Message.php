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
            'contents'   => $message->text ?? '',
            'uuid'       => $message->messageId,
            'created_at' => now(),
        ]);
    }

    public static function fromLLMMessage(LLMMessage $message): self
    {
        $messages = $message->getContents()->getMessages();

        return self::make([
            'role'     => $message->isUser() ? 'user' : ($message->isAssistant() ? 'assistant' : 'system'),
            'contents' => trim($messages[count($messages) - 1]?->getText() ?? ''),
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

    public static function systemIntroductoryMessage(bool $preload_memories = true): self
    {
        $template = file_get_contents(base_path('soul.md'));

        if ($preload_memories) {
            $important_memories = Memory::where('preload', true)->get();
            $other_memories = Memory::where('preload', false)->get();
        } else {
            $important_memories = collect();
            $other_memories = Memory::all();
        }

        $intro = Blade::render($template, [
            'OS'                 => PHP_OS_FAMILY === 'Darwin' ? 'macOS' : PHP_OS_FAMILY,
            'uname'              => php_uname('n'),
            'now'                => fn() => now(),
            'cwd'                => base_path(),
            'important_memories' => $important_memories,
            'other_memories'     => $other_memories,
            'bot_name'           => Setting::get('bot_name'),
            'user_first_name'    => Setting::get('user_first_name'),
            'user_last_name'     => Setting::get('user_last_name'),
        ], true);

        return self::make([
            'role'     => 'system',
            'contents' => $intro,
        ]);
    }

    public static function systemToolExecutionMessage(): self
    {
        $prompt = <<<MARKDOWN
    # IMPORTANT!

    This is a task execution session. Whatever you respond in this session will be used for logging only.
    If you agreed with the user or decide otherwise to notify the user about task completion results, use the notify-user-tool to do so!
    Avoid contacting user during their sleeping schedule if not necessary. You can also schedule a task to notify the user at a later time when they are more likely to be awake if you think it's best for the user experience.
MARKDOWN;

        return self::make([
            'role'     => 'system',
            'contents' => $prompt,
        ]);
    }

    public static function systemFileReceivedMessage(string $file_path, string $file_type): self
    {
        $finfo = finfo_open(FILEINFO_NONE);
        $file_info = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        $prompt = <<<MARKDOWN
    User has uploaded a $file_type file which was saved to $file_path. The file info is as follows:
    "$file_info"

    You can move it to user's Downloads folder or act on it differently if previously agreed with the user.
MARKDOWN;

        return self::make([
            'role'     => 'system',
            'contents' => $prompt,
        ]);
    }

}
