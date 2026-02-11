<?php

namespace App\Console\Commands;

use App\Console\Commands\Setup\SetupLLMModelHelper;
use App\Console\Commands\Setup\SetupLLMProviderHelper;
use App\Console\Commands\Setup\SetupTelegramHelper;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class LarabotConfigCommand extends Command
{
    use SetupTelegramHelper, SetupLLMProviderHelper, SetupLLMModelHelper;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:config {--sleep=2 : Seconds to sleep between steps}';

    protected int $sleep = 2;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Larabot settings.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->sleep = $this->option('sleep');

        if (!file_exists(base_path('.env'))) {
            $this->line('File .env not found. Creating from .env.example...');
            copy(base_path('.env.example'), base_path('.env'));
            $this->line('Generating application key...');
            Artisan::call('key:generate');
            $this->runSetupWizard();
        }

        $this->mainMenu();
    }

    protected function clearScreen(?string $title = null): void
    {
        echo "\033[2J\033[H";
        $this->billBoard('Larabot Configuration' . ($title ? " - $title" : ''));
    }

    protected function runSetupWizard()
    {
        $this->info('Let\'s set up your Larabot configuration.');

        $this->setupTelegram(false);
        $this->setupLLMProvider(false);
        $this->setupLLMModel(false);
//        $this->otherSettings();
        $this->mainMenu();
    }

    protected function billBoard(?string $message = 'Larabot Configuration!')
    {
        $this->info(str_repeat('=', strlen($message) + 6));
        $this->info('|' . str_repeat(' ', strlen($message) + 4) . '|');
        $this->info(strtoupper("|  $message  |"));
        $this->info('|' . str_repeat(' ', strlen($message) + 4) . '|');
        $this->info(str_repeat('=', strlen($message) + 6));
        $this->line('');
    }

    protected function mainMenu(): void
    {
        $this->clearScreen();

        $options = [
            'Telegram Bot (' . Setting::get('bot_name', 'Larabot') . ')',
            'LLM Provider (' . config('llm.default_provider', 'Not set') . ')',
            'LLM Model (' . config('models.default_model', 'Not set') . ')',
            'Other Settings',
            'Exit',
        ];

        $selected = $this->choice('What do you want to configure?', $options, 'Exit');

        match (true) {
            str_starts_with($selected, 'Telegram Bot') => $this->setupTelegram(),
            str_starts_with($selected, 'LLM Provider') => $this->setupLLMProvider(),
            str_starts_with($selected, 'LLM Model') => $this->setupLLMModel(),
            str_starts_with($selected, 'Other Settings') => $this->otherSettings(),
            default => $this->line('Exiting configuration. You can run this command again anytime to change settings.'),
        };
    }

    public function writeToEnv(string $key, string $value): bool|int
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return file_put_contents($envPath, "$key=" . $this->quoteString($value) . PHP_EOL);
        }

        $env = file($envPath, FILE_IGNORE_NEW_LINES);
        $keyFound = false;
        foreach ($env as &$line) {
            if (str_starts_with($line, "$key=")) {
                $line = "$key=" . $this->quoteString($value);
                $keyFound = true;
                break;
            }
        }
        unset($line);
        if (!$keyFound) {
            $env[] = "$key=" . $this->quoteString($value);
        }

        return file_put_contents($envPath, implode(PHP_EOL, $env) . PHP_EOL);
    }

    protected function quoteString(mixed $value): string
    {
        if (is_string($value) && !is_numeric($value)) {
            return '"' . addslashes($value) . '"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    public function sleep(?int $seconds = null): int
    {
        return sleep($seconds ?? $this->sleep);
    }


}
