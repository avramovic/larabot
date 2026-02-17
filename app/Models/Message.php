<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Soukicz\Llm\Message\LLMMessage;
use Telegram\Bot\Objects\Message as TelegramMessage;

/**
 * @property int $id
 * @property string $role
 * @property string $contents
 * @property string $uuid
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
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
            'uuid'     => (string) Str::uuid(),
        ]);
    }

    protected static function from(string $role, string $contents): self
    {
        return self::make([
            'role'     => $role,
            'contents' => $contents,
            'uuid'     => (string) Str::uuid(),
        ]);
    }

    public static function fromAssistant(string $message): self
    {
        return static::from('assistant', $message);
    }

    public static function fromUser(string $message): self
    {
        return static::from('user', $message);
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
            $important_memories = Memory::where('important', true)->get();
            $other_memories = Memory::where('important', false)->get();
        } else {
            $important_memories = collect();
            $other_memories = Memory::all();
        }

        $intro = Blade::render($template, [
            'OS'                 => PHP_OS_FAMILY === 'Darwin' ? 'macOS' : PHP_OS_FAMILY,
            'uname'              => php_uname(),
            'now'                => fn() => now(),
            'cwd'                => home_dir(),
            'important_memories' => $important_memories,
            'other_memories'     => $other_memories,
            'bot_name'           => Setting::get('bot_name'),
            'user_first_name'    => Setting::get('user_first_name'),
            'user_last_name'     => Setting::get('user_last_name'),
            'model'              => config('models.default_model'),
            'scheduled_tasks'    => Task::query()->get(),
        ], true);

        return self::make([
            'role'     => 'system',
            'contents' => $intro,
        ]);
    }

    public static function systemToolExecutionMessage(Task $task): self
    {
        $prompt = <<<MARKDOWN
    # IMPORTANT!

    - This is a task execution session. Do what the user asks you and then process the results with the tools at your disposal.
    - The task is not complete until you use a tool to send the result back to the user, save it as a memory OR
        save it to a file (and notify the user/memorize info about the file).
    - If you do not use any tool to process the results, the task will be considered failed and the response will not be visible to the user.
MARKDOWN;

        $msg = self::systemIntroductoryMessage(false);

        if ($task->destination === 'auto') {
            $msg->contents .= PHP_EOL . PHP_EOL . $prompt;
        }

        return $msg;
    }

    public static function systemFileReceivedMessage(
        string $file_path,
        string $file_type,
        Collection|TelegramMessage $telegram_message
    ): self {
        $file_info = get_file_info($file_path);

        $caption = (isset($telegram_message['caption']) && !empty($telegram_message['caption'])) ? 'Caption: ' . $telegram_message['caption'] . PHP_EOL : '';
        if ($telegram_message['caption'] ?? false) {
            $caption = ' captioned "' . $telegram_message['caption'] . '"';
        }

        $prompt = <<<MARKDOWN
    I have uploaded a $file_type file$caption, which was saved to "$file_path". The file info is as follows:
    "$file_info"

    You can move it to Downloads/Desktop/Documents folder or act on it differently if previously agreed (check caption
    above if any, and/or refer to previous conversation/memories).
MARKDOWN;

        return self::make([
            'role'     => 'user',
            'contents' => $prompt,
            'uuid'     => Str::uuid(),
        ]);
    }

}
