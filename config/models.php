<?php

return [
    'default_model' => env('LLM_MODEL', 'kimi-k2.5:cloud'),

    'models' => [
        'claude-3.5-haiku'  => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude35Haiku::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude35Haiku::VERSION_20241022,
        ],
        'claude-4.0-opus'   => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude4Opus::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude4Opus::VERSION_20250514,
        ],
        'claude-4.1-opus'   => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude41Opus::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude41Opus::VERSION_20250805,
        ],
        'claude-3.5-sonnet' => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude35Sonnet::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude35Sonnet::VERSION_20241022,
        ],
        'claude-3.7-sonnet' => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude37Sonnet::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude37Sonnet::VERSION_20250219,
        ],
        'claude-4.0-sonnet' => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude4Sonnet::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude4Sonnet::VERSION_20250514,
        ],
        'claude-4.5-sonnet' => [
            'class' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude45Sonnet::class,
            'model' => \Soukicz\Llm\Client\Anthropic\Model\AnthropicClaude45Sonnet::VERSION_20250929,
        ],

        'gpt-o.3'      => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPTo3::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPTo3::VERSION_2025_04_16,
        ],
        'gpt-o.4-mini' => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPTo4Mini::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPTo4Mini::VERSION_2025_04_16,
        ],
        'gpt-4.o'      => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT4o::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT4o::VERSION_2024_11_20,
        ],
        'gpt-4.o-mini' => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT4oMini::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT4oMini::VERSION_2024_07_18,
        ],
        'gpt-4.1'      => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT41::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT41::VERSION_2025_04_14,
        ],
        'gpt-4.1-mini' => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT41Mini::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT41Mini::VERSION_2025_04_14,
        ],
        'gpt-4.1-nano' => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT41Nano::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT41Nano::VERSION_2025_04_14,
        ],
        'gpt-5'        => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT5::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT5::VERSION_2025_08_07,
        ],
        'gpt-5-mini'   => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT5Mini::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT5Mini::VERSION_2025_08_07,
        ],
        'gpt-5-nano'   => [
            'class' => \Soukicz\Llm\Client\OpenAI\Model\GPT5Nano::class,
            'model' => \Soukicz\Llm\Client\OpenAI\Model\GPT5Nano::VERSION_2025_08_07,
        ],

        'gemini-2.0-flash'               => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini20Flash::class,
        ],
        'gemini-2.0-flash-lite'          => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini20FlashLite::class,
        ],
        'gemini-2.5-flash'               => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini25Flash::class,
        ],
        'gemini-2.5-flash-lite'          => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini25FlashLite::class,
        ],
        'gemini-2.5-flash-image-preview' => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini25FlashImagePreview::class,
        ],
        'gemini-2.5-pro'                 => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini25Pro::class,
        ],
        'gemini-2.5-pro-preview'         => [
            'class' => \Soukicz\Llm\Client\Gemini\Model\Gemini25ProPreview::class,
        ],

        // custom local model example, you can for example define ollama models here
        env('LLM_MODEL', 'kimi-k2.5:cloud') => [
            'class' => \Soukicz\Llm\Client\Universal\LocalModel::class,
            'model' => env('LLM_MODEL', 'kimi-k2.5:cloud'),
        ],
    ],
];
