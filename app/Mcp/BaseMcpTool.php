<?php

namespace App\Mcp;

use App\Channels\ChatInterface;

abstract class BaseMcpTool
{
    protected ChatInterface $chat;

    public function __construct()
    {
        $this->chat = app(ChatInterface::class);
    }
}
