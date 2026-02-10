<?php

namespace App\Console\Commands\Setup;

trait SetupTelegramHelper
{
    protected function setupTelegram(): void
    {
        $this->clearScreen('Telegram Bot');

        $this->line('Talk to BotFather on Telegram to create a new bot and get your API token: https://t.me/BotFather');

        $saved_token = config('channels.channels.telegram.bot_token');
        $token = $this->ask('Enter your Telegram Bot API token', $saved_token);

        if (!empty($token)) {
            if ($token !== $saved_token) {
                $this->line('Telegram Bot API token saved successfully.');
            }
            $this->line('Bot token didn\'t change. No updates made.');
        } else {
            $this->warn('No token entered. Telegram Bot configuration skipped.');
        }

        sleep(2);
        $this->mainMenu();
    }

}
