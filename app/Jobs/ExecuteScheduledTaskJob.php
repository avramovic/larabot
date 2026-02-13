<?php

namespace App\Jobs;

use App\Channels\ChatInterface;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Task;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\LLMResponse;
use Soukicz\Llm\Message\LLMMessage;

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
//            'file' => file_put_contents(base_path(Str::slug("Task #{$thixs->task->id} executed at " . now()->toDateTimeLocalString())),
//                $response->getLastText() ?? 'LLM responded with empty content.'),
            'auto' => null,
            default => $this->notifyUser($response),
        };

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

        Message::create([
            'role'     => 'assistant',
            'contents' => $text,
            'uuid'     => (string) Str::uuid(),
        ]);
        $this->chat->sendMessage($text);
    }

    protected function saveMemory(LLMResponse|string $response, ?string $title = null): void
    {
        $text = $response instanceof LLMResponse
            ? $response->getLastText() ?? 'LLM responded with empty content.'
            : $response;

        $title = $title ?? "Task #{$this->task->id} executed at " . now()->toDateTimeLocalString();

        Memory::create([
            'title'     => $title,
            'contents'  => $text,
            'important' => false,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        // handle failure, e.g. memorize the error, notify owner, etc.
        if ($this->task->destination === 'user') {
            $this->notifyUser("❌ Failed to execute scheduled task #{$this->task->id}: " . $exception->getMessage());
        } else {
            $this->saveMemory("An error occurred while executing scheduled task #{$this->task->id}: " . $exception->getMessage(),
                "Failed Task #{$this->task->id} at " . now()->toDateTimeLocalString());
        }
    }
}
