<?php

namespace App\Jobs;

use App\Channels\ChannelResolver;
use App\Models\Message;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIncomingMessageJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 1200;

    public function __construct(protected Message $message)
    {
    }

    public function handle(LLMChatService $chatService, ChannelResolver $channelResolver): void
    {
        $chat = $channelResolver->resolveForMessage($this->message);

        $sliding_window = (int) config('llm.sliding_window', -1);
        $conversation = $chatService->getConversation(
            $sliding_window,
            $this->message->channel_type,
            $this->message->channel_conversation_id,
            $this->message->id
        );

        try {
            $response = $chatService->send($conversation);
        } catch (\Exception $e) {
            \Log::error($e->getMessage(), $e->getTrace());
            $chat->sendMessage('❌ LLM request failed: ' . $e->getMessage());

            return;
        }

        $message_text = null;
        try {
            $message_text = $response->getLastText();
        } catch (\Throwable) {
            // no text in response
        }

        if ($message_text !== null && $message_text !== '') {
            $response_message = Message::fromAssistant($message_text);
            $response_message->channel_type = $this->message->channel_type;
            $response_message->channel_conversation_id = $this->message->channel_conversation_id;
            $response_message->save();
            $chat->sendMessage($message_text);
        } else {
            $chat->sendMessage('❌ LLM returned empty response. Stop reason: ' . $response->getStopReason()->value . '; try rephrasing your prompt.');
        }
    }

    public function failed(\Throwable $exception): void
    {
        try {
            $channelResolver = app(ChannelResolver::class);
            $chat = $channelResolver->resolveForMessage($this->message);
            $chat->sendMessage('❌ ' . $exception->getMessage());
        } catch (\Exception $e) {
            \Log::error('Failed to send error message to user: ' . $e->getMessage(), $e->getTrace());
        }
    }
}
