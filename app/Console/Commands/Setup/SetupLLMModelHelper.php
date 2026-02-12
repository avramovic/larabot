<?php

namespace App\Console\Commands\Setup;

use Illuminate\Support\Facades\Http;

trait SetupLLMModelHelper
{
    public function setupLLMModel(): void
    {
        $this->clearScreen('LLM Model Setup');

        $provider = $this->env('LLM_PROVIDER');
        $current = $this->env('LLM_MODEL');

        if ($provider === 'custom') {
            $model = $this->setupLLMModelForCustomProvider($current);
        } else {
            $model = $this->setupLLMModelForKnownProvider($provider, $current);
        }

        if ($model && $model != $current && $this->writeToEnv('LLM_MODEL', $model)) {
            $this->line('LLM model saved successfully.');
        }
    }

    protected function setupLLMModelForKnownProvider(string $provider, string $current): string|bool
    {
        $models = array_keys(config('models.models'));
        $model = $this->choice("Choose your $provider LLM model",
            array_merge($this->filterModels($models, $provider), ['Skip']), $current);

        if ($model === 'Skip') {
            $this->line('Skipped setting up LLM model.');
            return false;
        }

        return $model;
    }

    protected function setupLLMModelForCustomProvider(string $current): string|bool
    {
        $models = $this->fetchModels();
        $model = $this->choice("Choose your custom (OpenAI-compatible) LLM model",
            array_merge($models, ['Custom', 'Skip']), $current);

        if ($model === 'Skip') {
            $this->line('Skipped setting up LLM model.');
            return false;
        }

        if ($model === 'Custom') {
            $model = $this->ask('Enter your custom model EXACT name (e.g. llama3.1:latest or kimi-k2.5:cloud")');
        }

        return $model;
    }

    private function fetchModels(): array
    {
        $response = Http::get(config('llm.providers.custom.base_url') . '/models');
        if ($response->successful()) {
            $models = $response->json()['data'] ?? [];

            return array_map(function ($model) {
                return $model['id'] ?? null;
            }, $models);
        } else {
            $this->error('Failed to fetch models from custom provider. Please enter model name manually.');
            return [];
        }
    }

    protected function filterModels(array $models, string $filter = 'all'): array
    {
        $tag = match ($filter) {
            'openai' => 'gpt',
            'anthropic' => 'claude',
            'gemini' => 'gemini',
            default => null,
        };

        if (!is_null($tag)) {
            $models = array_filter($models, fn($model) => str_starts_with(strtolower($model), $tag));
        }

        return $models;
    }
}
