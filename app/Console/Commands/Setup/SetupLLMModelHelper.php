<?php

namespace App\Console\Commands\Setup;

trait SetupLLMModelHelper
{
    public function setupLLMModel(bool $returnToMenu = true): void
    {
        $this->clearScreen('LLM Model Setup');

        $provider = config('llm.default_provider');
        $current = config('models.default_model');

        $models = array_keys(config('models.models'));
        $model = $this->choice("Choose your LLM model",
            array_merge($this->filterModels($models, $provider), ['Custom', 'Skip']), $current);

        if ($model === 'List all...') {
            $this->clearScreen('LLM Model Setup - All Models');
            $model = $this->choice("Choose your LLM model", array_merge($models, ['Custom', 'Skip']), $current);
        }

        if ($model === 'Skip') {
            $this->line('Skipped setting up LLM model.');
            if ($returnToMenu) {
                $this->sleep();
                $this->mainMenu();
            } else {
                return;
            }
        }

        if ($model === 'Custom') {
            $this->clearScreen('LLM Model Setup - Custom Model');
            $model = $this->ask('Enter the EXACT name of your LLM model (for example: qwen2.5-coder or kimi-k2.5:cloud)', $current);
        }

        if ($model != $current && $this->writeToEnv('LLM_MODEL', $model)) {
            $this->line('LLM model saved successfully.');
        }

        if ($returnToMenu) {
            $this->sleep();
            $this->mainMenu();
        }
    }

    public function filterModels(array $models, string $filter = 'all'): array
    {
        $tag = match ($filter) {
            'openai' => 'gpt',
            'anthropic' => 'claude',
            'gemini' => 'gemini',
            default => null,
        };

        if (!is_null($tag)) {
            $models = array_filter($models, fn($model) => str_starts_with(strtolower($model), $tag));
            $models[] = 'List all...';
        }

        return $models;
    }
}
