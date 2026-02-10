<?php

namespace App\Jobs;

use App\Channels\Telegram\Telegram;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Task;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Soukicz\Llm\LLMConversation;

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
    public function handle(LLMChatService $chatService, Telegram $telegram): void
    {
        \Log::info("Executing scheduled task #{$this->task->id}: {$this->task->command}");
        $intro = Message::systemIntroductoryMessage();
        $message = Message::systemToolExecutionMessage($this->task);

        $response = $chatService->send(new LLMConversation([
            $intro->toLLMMessage(),
            $message->toLLMMessage(),
        ]));

        $telegram_chat_id = Setting::get('telegram_chat_id');

        try {
            $json_str = str_replace(['```json', '```'], '', $response->getLastText());
            $json = json_decode(trim($json_str));

            if ($json && $json->should_notify ?? true) {
                Message::create([
                    'role'     => 'assistant',
                    'contents' => $json->message ?? "✅ Task #{$this->task->id} executed successfully: " . $response->getLastText(),
                    'uuid'     => \Str::uuid(),
                ]);
                $telegram->sendMessage($telegram_chat_id,
                    $json->message ?? "✅ Task #{$this->task->id} executed successfully: " . $response->getLastText());
                \Log::info("Task #{$this->task->id} executed successfully and a notification was sent to Telegram.",
                    (array) $json);
            } else {
                \Log::info("Task #{$this->task->id} executed successfully, but no notification was sent to Telegram as per the LLM response.",
                    $json);
            }

        } catch (\Exception $e) {
            \Log::error("Failed to parse LLM response as JSON: " . $e->getMessage(), [
                'response_text' => $response->getLastText(),
            ]);

            $telegram->sendMessage($telegram_chat_id,
                "✅ Task #{$this->task->id} executed successfully but the response could not be parsed as JSON. Response was: " . $response->getLastText());
        }

        if ($this->task->repeat > 0) {
            $this->task->decrement('repeat');
        }
    }
}
