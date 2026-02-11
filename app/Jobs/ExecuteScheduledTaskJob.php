<?php

namespace App\Jobs;

use App\Channels\ChatInterface;
use App\Channels\Telegram\Telegram;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Task;
use App\Services\LLMChatService;
use App\Support\LlmJsonExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        $intro = Message::systemIntroductoryMessage(false);
        $message = Message::systemToolExecutionMessage();

        $chat->sendChatAction();

        $conversation = new LLMConversation([
            $intro->toLLMMessage(),
            $message->toLLMMessage(),
            LLMMessage::createFromUserString($this->task->prompt),
        ]);

        \Log::debug('Executing LLM conversation for task #'.$this->task->id . ': ', ['convo' => $conversation->jsonSerialize()]);

        $response = $chatService->send($conversation, true);

        \Log::debug('LLM scheduled task response:', [
            'role'     => 'assistant',
            'contents' => $json->message ?? $response->getLastText(),
            'uuid'     => \Str::uuid(),
        ]);

        if ($this->task->repeat > 0) {
            $this->task->decrement('repeat');
        }
    }
}
