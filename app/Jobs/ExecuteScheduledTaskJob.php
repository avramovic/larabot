<?php

namespace App\Jobs;

use App\Channels\ChatInterface;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskExecutionLog;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\LLMResponse;
use Soukicz\Llm\Message\LLMMessage;
use Soukicz\Llm\Message\LLMMessageArrayData;
use Soukicz\Llm\Message\LLMMessageContents;
use Soukicz\Llm\Message\LLMMessageText;
use Soukicz\Llm\Message\LLMMessageToolResult;
use Soukicz\Llm\Message\LLMMessageToolUse;

class ExecuteScheduledTaskJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 1200;

    protected ChatInterface $chat;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Task $task)
    {
        $this->chat = app(ChatInterface::class);
    }

    /**
     * Execute the job.
     */
    public function handle(LLMChatService $chatService): void
    {
        \Log::info("Executing scheduled task #{$this->task->id}: {$this->task->prompt}");
        $intro = Message::systemToolExecutionMessage($this->task);

        $this->chat->sendChatAction();

        $conversation = new LLMConversation([
            $intro->toLLMMessage(),
            LLMMessage::createFromUserString($this->task->prompt),
        ]);

        \Log::debug('Executing LLM conversation for task #' . $this->task->id . ': ',
            ['convo' => $conversation->jsonSerialize()]);

        $response = $chatService->send($conversation, true);

        match ($this->task->destination) {
            'memory' => $this->saveMemory($response),
            'auto' => null,
            default => $this->notifyUser($response),
        };

        $outputText = 'LLM responded with empty content.';
        try {
            $outputText = $response->getLastText() ?? $outputText;
        } catch (\Throwable) {
            // keep fallback
        }

        TaskExecutionLog::create([
            'task_id'     => $this->task->id,
            'output_text' => $outputText,
            'tool_calls'  => $this->extractToolCalls($response),
            'status'      => 'success',
        ]);

        \Log::debug('LLM scheduled task response:', [
            'text' => $response->getLastText(),
        ]);

        if ($this->task->repeat > 0) {
            $this->task->decrement('repeat');
        }
    }

    protected function notifyUser(LLMResponse|string $response): void
    {
        $text = $response instanceof LLMResponse
            ? $response->getLastText() ?? "❌ Task #{$this->task->id} executed, but no response received."
            : $response;

        $chatId = Setting::get('telegram_chat_id');

        Message::create([
            'role'                    => 'assistant',
            'contents'                => $text,
            'uuid'                    => (string) Str::uuid(),
            'channel_type'            => Message::CHANNEL_TELEGRAM,
            'channel_conversation_id' => $chatId !== null ? (string) $chatId : null,
        ]);
        $this->chat->sendMessage($text);
    }

    protected function saveMemory(LLMResponse|string $response, ?string $title = null, bool $important = true, ?string $text_prefix = null): void
    {
        $text = $response instanceof LLMResponse
            ? $response->getLastText() ?? 'LLM responded with empty content.'
            : $response;

        if ($text_prefix) {
            $text = $text_prefix . PHP_EOL . PHP_EOL . $text;
        }

        $title = $title ?? "Task #{$this->task->id} executed successfully at " . now()->toDateTimeLocalString();

        Memory::create([
            'title'     => $title,
            'contents'  => $text,
            'important' => $important,
        ]);
    }

    /**
     * @return array<int, array{tool: string, input: array, result: string|array|null}>
     */
    protected function extractToolCalls(LLMResponse $response): array
    {
        $messages = $response->getConversation()->getMessages();
        $ordered  = [];
        $results  = [];

        foreach ($messages as $message) {
            foreach ($message->getContents()->getMessages() as $content) {
                if ($content instanceof LLMMessageToolUse) {
                    $ordered[] = [
                        'id'    => $content->getId(),
                        'tool'  => $content->getName(),
                        'input' => $content->getInput(),
                    ];
                    $results[$content->getId()] = null;
                }
                if ($content instanceof LLMMessageToolResult) {
                    $results[$content->getId()] = $this->serializeToolResultContent($content->getContent());
                }
            }
        }

        return array_map(fn (array $o): array => [
            'tool'   => $o['tool'],
            'input'  => $o['input'],
            'result' => $results[$o['id']] ?? null,
        ], $ordered);
    }

    /**
     * @return string|array
     */
    protected function serializeToolResultContent(LLMMessageContents $content): string|array
    {
        $out = [];
        foreach ($content->getMessages() as $part) {
            if ($part instanceof LLMMessageText) {
                return $part->getText();
            }
            if ($part instanceof LLMMessageArrayData) {
                return $part->getData();
            }
        }

        return '';
    }

    public function failed(\Throwable $exception): void
    {
        TaskExecutionLog::create([
            'task_id'     => $this->task->id,
            'output_text' => "Failed to execute scheduled task #{$this->task->id}: " . $exception->getMessage(),
            'tool_calls'  => null,
            'status'      => 'failed',
        ]);

        if ($this->task->destination === 'user') {
            $this->notifyUser("❌ Failed to execute scheduled task #{$this->task->id}: " . $exception->getMessage());
        }
    }
}
