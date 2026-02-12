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
use Soukicz\Llm\Message\LLMMessage;

class ExecuteScheduledTaskJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Task $task)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(LLMChatService $chatService, ChatInterface $chat): void
    {
        \Log::info("Executing scheduled task #{$this->task->id}: {$this->task->prompt}");
        $intro = Message::systemToolExecutionMessage();

        $chat->sendChatAction();

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
            default => $chat->sendMessage($response->getLastText() ?? "❌ Task #{$this->task->id} executed, but no response received."),
        };

        \Log::debug('LLM scheduled task response:', [
            'text' => $response->getLastText(),
        ]);

        if ($this->task->repeat > 0) {
            $this->task->decrement('repeat');
        }
    }
}
