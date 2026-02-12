<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Soukicz\Llm\LLMConversation;
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
            'uname'              => php_uname(),
            'now'                => fn() => now(),
            'cwd'                => home_dir(),
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

    public static function systemToolExecutionMessage(bool $is_auto = false): self
    {
        $bot_name = Setting::get('bot_name');
        $prompt = <<<MARKDOWN
    # IMPORTANT!

    - This is a task execution session. Whatever you respond in this session will be used for logging only.
    - You MUST decide what tools to execute depending on what you want to do with the results - notify user, remember for
        later use or store as a document in the appropriate folder. When saving files, make
        sure to give them descriptive names and txt extension if they are text files, so that you can easily find them later.
MARKDOWN;

        $msg = self::systemIntroductoryMessage(false);

        if ($is_auto) {
            $msg->contents .= PHP_EOL . PHP_EOL . $prompt;
        }

        return $msg;
    }

    public static function systemFileReceivedMessage(string $file_path, string $file_type): self
    {
        $finfo = finfo_open(FILEINFO_NONE);
        $file_info = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        $prompt = <<<MARKDOWN
    I have uploaded a $file_type file which was saved to $file_path. The file info is as follows:
    "$file_info"

    You can move it to Downloads/Desktop/Documents folder or act on it differently if previously agreed.
MARKDOWN;

        return self::make([
            'role'     => 'user',
            'contents' => $prompt,
            'uuid'     => Str::uuid(),
        ]);
    }

}
