<?php

return [
    'default_provider' => env('LLM_PROVIDER', 'custom'),

    'cache_prompts' => true,
    'sliding_window' => env('SLIDING_WINDOW_SIZE', 50),

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
        ],
        'openai'    => [
            'api_key' => env('OPENAI_API_KEY'),
            'org_key' => env('OPENAI_ORG_KEY'),
        ],
        'gemini'    => [
            'api_key' => env('GEMINI_API_KEY'),
        ],
        // Custom OpenAI compatible provider example, you can for example define ollama models here
        'custom'    => [
            'api_key'  => env('CUSTOM_API_KEY', 'ollama'),
            'base_url' => env('CUSTOM_BASE_URL', 'http://localhost:11434/v1'),
        ],
    ],
];
