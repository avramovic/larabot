<?php

namespace App\Jobs;

use App\Channels\ChatInterface;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Task;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\Message\LLMMessage;

class ExecuteScheduledTaskJob implements ShouldQueue
{
    use Queueable;

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
        $intro = Message::systemToolExecutionMessage($this->task->destination === 'auto');

        $this->chat->sendChatAction();

        $conversation = new LLMConversation([
            $intro->toLLMMessage(),
            LLMMessage::createFromUserString($this->task->prompt),
        ]);

        \Log::debug('Executing LLM conversation for task #'.$this->task->id . ': ', ['convo' => $conversation->jsonSerialize()]);

        $response = $chatService->send($conversation, true);

        match ($this->task->destination) {
            'memory' => Memory::create([
                'title'    => "Task #{$this->task->id} executed at " . now()->toDateTimeLocalString(),
                'contents' => $response->getLastText() ?? 'LLM responded with empty content.',
                'preload'  => false,
            ]),
//            'file' => file_put_contents(base_path(Str::slug("Task #{$thixs->task->id} executed at " . now()->toDateTimeLocalString())),
//                $response->getLastText() ?? 'LLM responded with empty content.'),
            'auto' => null,
            default => $this->chat->sendMessage($response->getLastText() ?? "❌ Task #{$this->task->id} executed, but no response received."),
        };

        \Log::debug('LLM scheduled task response:', [
            'text' => $response->getLastText(),
        ]);

        if ($this->task->repeat > 0) {
            $this->task->decrement('repeat');
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->task->destination === 'user') {
            $this->chat->sendMessage("❌ Failed to execute scheduled task #{$this->task->id}: " . $exception->getMessage());
        } else {
            // Log the error to bot's memory for later review
            Memory::create([
                'title'    => "Failed Task #{$this->task->id} at " . now()->toDateTimeLocalString(),
                'contents' => "An error occurred while executing scheduled task #{$this->task->id}: " . $exception->getMessage(),
                'preload'  => false,
            ]);
        }
    }
}
