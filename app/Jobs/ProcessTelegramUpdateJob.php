<?php

namespace App\Jobs;

use App\Channels\Telegram\Telegram;
use App\Models\Message;
use App\Models\Setting;
use App\Services\LLMChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Soukicz\Llm\Client\StopReason;
use Telegram\Bot\Objects\Update;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    protected ?Telegram $telegram = null;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Update $update)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(LLMChatService $chatService): void
    {
        $telegram = new Telegram($this->update);

        $bot_info = $telegram->getBotInfo();
        Setting::updateOrCreate(
            ['key' => 'telegram_bot_username'],
            ['value' => $bot_info->first_name],
        );

        \Log::debug('Processing Telegram update: ', $this->update->toArray());

        //safety check to ensure we only process messages from the configured chat and owner
        $telegram_user = $telegram->getUpdate('from');
        $telegram_chat = $telegram->getUpdate('chat');

        if (empty($telegram->owner_id)) {
            Setting::set('telegram_owner_id', $telegram_user->id);
            Setting::set('user_first_name', $telegram_user->first_name);
            Setting::set('user_last_name', $telegram_user->last_name);
            $telegram->owner_id = $telegram_user->id;
            $telegram->sendMessage($telegram_chat->id, "👋 Hi {$telegram_user->first_name}! You've been set as the owner of this Telegram bot. You can now start sending messages to the bot and it will respond using the LLM.");

            if (empty($telegram->chat_id)) {
                Setting::set('telegram_chat_id', $telegram_chat->id);
                $telegram->chat_id = $telegram_chat->id;
            }

            return;
        }


        if ($telegram_user->id != $telegram->owner_id) {
            \Log::warning("Received message from unauthorized source:", $telegram->getUpdate()->toArray());
            $telegram->sendMessage($telegram_user->id, "❌ {$telegram_user->first_name} is not in the sudoers file. This incident will be reported.");
            $telegram->sendMessage($telegram_user->id, "But you can get your own personal AI assistant from https://github.com/avramovic/larabot and set yourself as the owner of your own bot to start chatting with it!");
            return;
        }

        $telegram_message = $this->update->getMessage();
        if (Message::where('uuid', $telegram_message->messageId)->exists()) {
            \Log::warning("Received duplicate message, ignoring:", ['message_id' => $telegram_message->id]);
            return;
        }

        $message = Message::fromTelegramMessage($telegram_message);
        $conversation = $chatService->getConversation(config('llm.sliding_window', -1));
        $conversation = $conversation->withMessage($message->toLLMMessage());

        try {
            $response = $chatService->send($conversation);
        } catch (\Exception $e) {
            \Log::error($e->getMessage(), $e->getTrace());
            $telegram->sendMessage(
                $telegram_chat->id,
                "❌ Sorry, something went wrong while processing your request: " . $e->getMessage()
            );

            return;
        }

        if ($response->getStopReason() == StopReason::TOOL_USE) {
            // Add the assistant's response (including tool use) to conversation
            $tool_message = Message::fromLLMMessage($response->getConversation()->getLastMessage());
            $tool_message->save();
            $conversation = $conversation->withMessage($tool_message->toLLMMessage());

            \Log::debug('LLM requested tool use, processing tools...', $response->getConversation()->getLastMessage()->jsonSerialize());

            // Execute the tool and add result
//            foreach ($response->getLastMessage()->getContents()->getToolUses() as $toolUse) {
//                $tool = $tools[$toolUse->getName()] ?? null;
//
//                if ($tool) {
//                    $result = $tool->handle($toolUse->getInput());
//                    $conversation = $conversation->withMessage(
//                        LLMMessage::createFromUser(
//                            new LLMMessageContents([
//                                new LLMMessageToolResult(
//                                    toolUseId: $toolUse->getId(),
//                                    content: $result,
//                                    isError: false
//                                )
//                            ])
//                        )
//                    );
//                }
//            }

            // Continue the conversation
//            $finalResponse = $agentClient->run(
//                client: $client,
//                request: new LLMRequest(
//                    model: $model,
//                    conversation: $conversation,
//                    tools: [$calculator]
//                )
//            );
//
//            echo $finalResponse->getLastText();
        }

        $message->save();
        Setting::set('telegram_offset', $this->update['update_id']);

        $response_message = Message::fromLLMMessage($response->getConversation()->getLastMessage());
        $response_message->save();

        $telegram->sendMessage($telegram_chat->id, $response->getLastText());

    }

}
