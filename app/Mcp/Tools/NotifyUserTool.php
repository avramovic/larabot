<?php

namespace App\Mcp\Tools;

use App\Mcp\BaseMcpTool;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class NotifyUserTool extends BaseMcpTool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Notify the user from outside the conversation. This can be used to send important updates or alerts to the user,
        even when they are not actively engaged in a conversation with the assistant.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $message = $request->get('message');

        Message::create([
            'role' => 'assistant',
            'contents' => $message,
            'uuid' => \Str::uuid(),
        ]);

        $this->chat->sendMessage($message);

        return Response::text('The user has been notified with the message: ' . $message);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->description('REQUIRED. The message to send to the user.')->required(),
        ];
    }
}
