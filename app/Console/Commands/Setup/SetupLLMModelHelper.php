<?php

namespace App\Console\Commands\Setup;

trait SetupLLMModelHelper
{
    public function setupLLMModel()
    {
        $this->clearScreen('LLM Model Setup');

        $provider = config('llm.default_provider');
        $current = config('models.default_model');

        $models = array_keys(config('models.models'));
        $model = $this->choice("Choose your LLM model",
            array_merge($this->filterModels($models, $provider), ['All']), $current);

        if ($model === 'All') {
            $model = $this->choice("Choose your LLM model", $models, $current);
        }

        if ($this->writeToEnv('LLM_MODEL', $model)) {
            $this->line('LLM model saved successfully.');
        }

        $this->mainMenu();
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
        }

        return array_merge($models, ['Custom']);
    }
}
