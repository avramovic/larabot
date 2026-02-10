<?php

namespace App\Mcp;

use App\Channels\ChatInterface;
use Laravel\Mcp\Server\Tool;

abstract class BaseMcpTool extends Tool
{
    protected ChatInterface $chat;

    public function __construct()
    {
        $this->chat = app(ChatInterface::class);
    }
}
