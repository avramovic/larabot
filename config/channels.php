<?php

return [
    'active_channel' => env('ACTIVE_CHANNEL', 'telegram'),
    'channels'       => [
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        ]
    ],
];
