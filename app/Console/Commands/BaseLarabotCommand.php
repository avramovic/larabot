<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class BaseLarabotCommand extends Command
{
    protected function clearScreen(?string $title = null): void
    {
        echo "\033[2J\033[H";
        $this->billBoard('Larabot' . ($title ? " - $title" : ''));
    }

    protected function billBoard(?string $message = 'Larabot')
    {
        $this->info(str_repeat('=', strlen($message) + 6));
        $this->info('|' . str_repeat(' ', strlen($message) + 4) . '|');
        $this->info(strtoupper("|  $message  |"));
        $this->info('|' . str_repeat(' ', strlen($message) + 4) . '|');
        $this->info(str_repeat('=', strlen($message) + 6));
        $this->line('');
    }
}
