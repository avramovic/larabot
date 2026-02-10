<?php

namespace App\Console\Commands\Setup;

trait SetupLLMProviderHelper
{
    public function setupLLMProvider(): void
    {
        $this->clearScreen('LLM Provider Setup');

        $current = config('llm.default_provider');

        $preselected = match ($current) {
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            default => 'OpenAI-compatible (ollama)',
        };

        $provider = $this->choice('Select your LLM provider',
            ['OpenAI', 'Anthropic', 'Google Gemini', 'OpenAI-compatible (ollama)', 'Skip'], $preselected);

        switch ($provider) {
            case 'OpenAI':
                $this->setupOpenAI();
                break;
            case 'Anthropic':
                $this->setupAnthropic();
                break;
            case 'Google Gemini':
                $this->setupGoogleGemini();
                break;
            case 'OpenAI-compatible (ollama)':
                $this->setupCustomLLMProvider();
                break;
            default:
                $this->line('LLM provider setup skipped.');
                $this->mainMenu();
        }
    }


    protected function setupOpenAI()
    {
        $this->clearScreen('LLM Provider Setup - OpenAI');

        $api_key = $this->ask('Your OpenAI API Key', config('llm.providers.openai.api_key'));

        if ($this->writeToEnv('OPENAI_API_KEY', $api_key)) {
            $this->line('OpenAI API key saved successfully.');
        }

        $org_key = $this->ask('Your OpenAI Organization Key', config('llm.providers.openai.org_key'));

        if ($this->writeToEnv('OPENAI_ORG_KEY', $org_key)) {
            $this->line('OpenAI Organization key saved successfully.');
        }

        $this->setupLLMProvider();
    }

    protected function setupAnthropic()
    {
        $this->clearScreen('LLM Provider Setup - Anthropic');

        $api_key = $this->ask('Your Anthropic API Key', config('llm.providers.anthropic.api_key'));

        if ($this->writeToEnv('ANTHROPIC_API_KEY', $api_key)) {
            $this->line('Anthropic API key saved successfully.');
        }

        $this->setupLLMProvider();
    }

    protected function setupGoogleGemini()
    {
        $this->clearScreen('LLM Provider Setup - Google Gemini');

        $api_key = $this->ask('Your Google Gemini API Key', config('llm.providers.gemini.api_key'));

        if ($this->writeToEnv('GEMINI_API_KEY', $api_key)) {
            $this->line('Google Gemini API key saved successfully.');
        }

        $this->setupLLMProvider();
    }

    protected function setupCustomLLMProvider(): void
    {
        $this->clearScreen('LLM Provider Setup - Custom');

        $api_key = $this->ask('Your OpenAI-compatible API Key', config('llm.providers.custom.api_key'));

        if ($this->writeToEnv('CUSTOM_API_KEY', $api_key)) {
            $this->line('Google OpenAI-compatible key saved successfully.');
        }

        $base_url = $this->ask('Your OpenAI-compatible API Base URL', config('llm.providers.custom.base_url'));

        if ($this->writeToEnv('CUSTOM_BASE_URL', $base_url)) {
            $this->line('Google OpenAI-compatible API Base URL saved successfully.');
        }

        $this->setupLLMProvider();
    }
}
