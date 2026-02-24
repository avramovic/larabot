<?php

namespace App\Console\Commands\Setup;

use App\Models\Setting;
use Telegram\Bot\Api;

trait SetupOtherHelper
{
    protected function otherSettings(): void
    {
        $this->clearScreen('Other Settings');

        $options = $this->otherMenuOptions();

        while ($selected = $this->choice('Choose a setting to update', $options, 'Exit')) {
            $exit = match (true) {
                str_starts_with($selected, 'Time Zone') => $this->setupTimeZone(),
                str_starts_with($selected, 'Brave Search') => $this->simpleTextSetup('BRAVE_SEARCH_API_KEY', 'Brave Search API Key'),
                str_starts_with($selected, 'Sliding Window') => $this->simpleTextSetup('SLIDING_WINDOW_SIZE', 'Sliding Window Size', 'number'),
                str_starts_with($selected, 'Cache Prompts') => $this->simpleConfirmSetup('CACHE_PROMPTS', 'Cache Prompts'),
                str_starts_with($selected, 'Show tool execution') => $this->simpleConfirmSetup('SHOW_TOOL_EXECUTION_LOGS', 'Show tool execution logs in chat'),
                default => true,
            };

            if ($exit) {
                break;
            }

            $options = $this->otherMenuOptions();
            $this->sleep();
            $this->clearScreen('Other Settings');
        }
    }

    protected function otherMenuOptions(): array
    {
        return [
            'Time Zone (' . $this->env('APP_TIMEZONE') . ')',
            'Brave Search API (' . ucwords($this->env('BRAVE_SEARCH_API_KEY', '[not set]')) . ')',
            'Sliding Window Size (' . $this->env('SLIDING_WINDOW_SIZE') . ')',
            'Cache Prompts (' . ($this->env('CACHE_PROMPTS') === 'true' ? 'Enabled' : 'Disabled') . ')',
            'Show tool execution logs in chat (' . ($this->env('SHOW_TOOL_EXECUTION_LOGS') === 'true' ? 'Yes' : 'No') . ')',
            'Back',
        ];
    }


    public function setupTimeZone(): bool
    {
        $timezones = \DateTimeZone::listIdentifiers();
        $current = $this->env('APP_TIMEZONE', 'UTC');

        // Split timezones into continents and cities
        $continents = [];
        foreach ($timezones as $tz) {
            $parts = explode('/', $tz, 2);
            if (count($parts) === 2) {
                $continents[$parts[0]][] = $parts[1];
            }
        }
        $continentList = array_keys($continents);
        sort($continentList);
        $continentList[] = 'Skip';

        while (true) {
            $continent = $this->choice('Select your continent', $continentList, $current ? explode('/', $current)[0] : null);
            if ($continent === 'Skip') {
                $this->line('Skipped setting up time zone.');
                return false;
            }
            if (!isset($continents[$continent])) {
                continue;
            }
            $cities = $continents[$continent];
            sort($cities);
            $cityList = $cities;
            $cityList[] = 'Back';
            $cityList[] = 'Skip';
            $currentCity = $current && strpos($current, $continent . '/') === 0 ? substr($current, strlen($continent) + 1) : null;
            $city = $this->choice('Select your city', $cityList, $currentCity);
            if ($city === 'Back') {
                continue;
            }
            if ($city === 'Skip') {
                $this->line('Skipped setting up time zone.');
                return false;
            }
            $timezone = $continent . '/' . $city;
            if ($timezone && $timezone != $current && $this->writeToEnv('APP_TIMEZONE', $timezone)) {
                $this->line('Time zone saved successfully.');
            }
            return false;
        }
    }

    public function simpleTextSetup(string $env_key, string $question, string|array $validation = 'string'): bool
    {
        $default = $this->env($env_key);
        while (true) {
            $input = $this->ask($question . ($default ? " [{$default}]" : ''));
            if ($input === null || $input === '') {
                $input = $default;
            }
            // Map validation argument to Laravel rules
            $rules = [];
            if (is_string($validation)) {
                if ($validation === 'number') {
                    $rules[] = 'numeric';
                } else {
                    $rules[] = $validation;
                }
            } elseif (is_array($validation)) {
                $rules = $validation;
            }
            $validator = validator(['value' => $input], ['value' => $rules]);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                continue;
            }
            $this->writeToEnv($env_key, $input);
            $this->line('Value saved successfully.');
            break;
        }
        return false;
    }

    public function simpleConfirmSetup(string $env_key, string $question): bool
    {
        $default = $this->env($env_key);
        $confirm = $this->confirm($question . ' (current: '.($default == 'true' ? 'yes' : 'no').')');
        $this->writeToEnv($env_key, $confirm ? 'true' : 'false');
        $this->line('Value saved successfully.');
        return false;
    }

}
