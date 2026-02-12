<?php

namespace App\Console\Commands\Setup;

use App\Models\Setting;
use Telegram\Bot\Api;

trait SetupTelegramHelper
{
    protected function setupTelegram(): void
    {
        $this->clearScreen('Telegram Bot');

        $this->line('Talk to BotFather on Telegram to create a new bot and get your API token: https://t.me/BotFather');

        $saved_token = $this->env('TELEGRAM_BOT_TOKEN');
        $token = $this->ask('Enter your Telegram Bot API token', $saved_token);

        if (!empty($token)) {
            if ($token !== $saved_token) {
                $this->writeToEnv('TELEGRAM_BOT_TOKEN', $token);
                $this->line('Telegram Bot API token saved successfully.');
                $this->alert('Talk to your bot on Telegram after completing this step to claim the ownership!');
                $this->getBotInfo($token);
                sleep(10);
            } else {
                $this->line('Bot token didn\'t change. No updates made.');
            }
        } else {
            $this->warn('No token entered. Telegram Bot configuration skipped.');
        }
    }

    protected function getBotInfo(string $token): void
    {
        $telegram = new Api($token);
        $bot_info = $telegram->getMe();
        Setting::updateOrCreate(
            ['key' => 'bot_name'],
            ['value' => $bot_info->first_name],
        );
    }

}
