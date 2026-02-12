<?php

namespace App\Console\Commands\Setup;

trait SetupLLMProviderHelper
{
    public function setupLLMProvider(): void
    {
        $this->clearScreen('LLM Provider Setup');

        $current = $this->env('LLM_PROVIDER');
        $preselected = $this->beautifyProviderName($current);

        $provider = $this->choice('Select your LLM provider',
            ['OpenAI', 'Anthropic', 'Google Gemini', 'Custom (OpenAI-compatible)', 'Skip'], $preselected);

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
            case 'Custom (OpenAI-compatible)':
                $this->setupCustomLLMProvider();
                break;
            default:
                $this->line('LLM provider setup skipped.');
        }

        $this->sleep();
        $this->setupLLMModel();
    }

    protected function setupOpenAI()
    {
        $this->clearScreen('LLM Provider Setup - OpenAI');

        $current_api_key = $this->env('OPENAI_API_KEY');
        $api_key = $this->ask('Your OpenAI API Key', $current_api_key);

        if ($api_key != $current_api_key && $this->writeToEnv('OPENAI_API_KEY', $api_key)) {
            $this->line('OpenAI API key saved successfully.');
        }

        $current_org_key = $this->env('OPENAI_ORG_KEY');
        $org_key = $this->ask('Your OpenAI Organization Key', $current_org_key);

        if ($org_key != $current_org_key && $this->writeToEnv('OPENAI_ORG_KEY', $org_key)) {
            $this->line('OpenAI Organization key saved successfully.');
        }

        if ($api_key && $org_key && $this->writetoEnv('LLM_PROVIDER', 'openai')) {
            $this->line('OpenAI set as the LLM provider.');
        }
    }

    protected function setupAnthropic()
    {
        $this->clearScreen('LLM Provider Setup - Anthropic');

        $current_api_key = $this->env('ANTHROPIC_API_KEY');
        $api_key = $this->ask('Your Anthropic API Key', $current_api_key);

        if ($api_key != $current_api_key && $this->writeToEnv('ANTHROPIC_API_KEY', $api_key)) {
            $this->line('Anthropic API key saved successfully.');
        }

        if ($api_key && $this->writetoEnv('LLM_PROVIDER', 'anthropic')) {
            $this->line('Anthropic set as the LLM provider.');
        }
    }

    protected function setupGoogleGemini()
    {
        $this->clearScreen('LLM Provider Setup - Google Gemini');

        $current_api_key = $this->env('GEMINI_API_KEY');
        $api_key = $this->ask('Your Google Gemini API Key', $current_api_key);

        if ($api_key != $current_api_key && $this->writeToEnv('GEMINI_API_KEY', $api_key)) {
            $this->line('Google Gemini API key saved successfully.');
        }

        if ($this->writetoEnv('LLM_PROVIDER', 'gemini')) {
            $this->line('Google Gemini set as the LLM provider.');
        }
    }

    protected function setupCustomLLMProvider(): void
    {
        $this->clearScreen('LLM Provider Setup - Custom (Open-AI compatible)');

        $current_api_key = $this->env('CUSTOM_API_KEY');
        $api_key = $this->ask('Your OpenAI-compatible API Key', $current_api_key);

        if ($current_api_key != $api_key && $this->writeToEnv('CUSTOM_API_KEY', $api_key)) {
            $this->line('Custom (OpenAI-compatible) API key saved successfully.');
        }

        $current_base_url = $this->env('CUSTOM_BASE_URL');
        $base_url = $this->ask('Your OpenAI-compatible API Base URL', $current_base_url);

        if ($current_base_url != $base_url && $this->writeToEnv('CUSTOM_BASE_URL', $base_url)) {
            $this->line('Custom (OpenAI-compatible) API base URL saved successfully.');
        }

        if ($api_key && $base_url && $this->writetoEnv('LLM_PROVIDER', 'custom')) {
            $this->line('Custom (OpenAI-compatible) set as the LLM provider.');
        }
    }
}
