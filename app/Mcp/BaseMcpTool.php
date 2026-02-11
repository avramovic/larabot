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

    protected function checkTruthiness(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);
            return in_array($lowerValue, ['true', '1', 'yes'], true);
        }

        if (is_numeric($value)) {
            return $value == 1;
        }

        return false;
    }

}
