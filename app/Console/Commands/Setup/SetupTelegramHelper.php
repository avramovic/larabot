<?php

namespace App\Console\Commands\Setup;

trait SetupTelegramHelper
{
    protected function setupTelegram(bool $returnToMenu = true): void
    {
        $this->clearScreen('Telegram Bot');

        $this->line('Talk to BotFather on Telegram to create a new bot and get your API token: https://t.me/BotFather');

        $saved_token = config('channels.channels.telegram.bot_token');
        $token = $this->ask('Enter your Telegram Bot API token', $saved_token);

        if (!empty($token)) {
            if ($token !== $saved_token) {
                $this->writeToEnv('TELEGRAM_BOT_TOKEN', $token);
                $this->line('Telegram Bot API token saved successfully.');
                $this->alert('Talk to your bot NOW on Telegram to complete the setup! Whoever messages the bot first will become the owner and will be able to chat with it!');
                while (!$this->ask('Have you messaged the bot on Telegram and completed the setup?')) {
                    $this->warn('Please message your bot on Telegram to complete the setup. This is required to link the bot to your Telegram account and allow it to function properly.');
                }
            } else {
                $this->line('Bot token didn\'t change. No updates made.');
            }
        } else {
            $this->warn('No token entered. Telegram Bot configuration skipped.');
        }

        if ($returnToMenu) {
            $this->sleep();
            $this->mainMenu();
        }
    }

}
