<?php

namespace App\Console\Commands;

use App\Channels\Telegram\Telegram;
use App\Console\Commands\Setup\SetupLLMModelHelper;
use App\Console\Commands\Setup\SetupLLMProviderHelper;
use App\Console\Commands\Setup\SetupOtherHelper;
use App\Console\Commands\Setup\SetupTelegramHelper;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

class LarabotConfigCommand extends BaseLarabotCommand
{
    use SetupTelegramHelper, SetupLLMProviderHelper, SetupLLMModelHelper, SetupOtherHelper;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larabot:config {--sleep=2 : Seconds to sleep between steps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Larabot settings.';

    protected array $env;
    protected int $sleep = 2;
    public ?Telegram $telegram = null;

    public function __construct()
    {
        parent::__construct();
        $this->env = [
            'TELEGRAM_BOT_TOKEN'   => config('channels.channels.telegram.bot_token'),
            'LLM_PROVIDER'         => config('llm.default_provider'),
            'LLM_MODEL'            => config('models.default_model'),
            'BRAVE_SEARCH_API_KEY' => config('services.brave_search.api_key'),
            'OPENAI_API_KEY'       => config('llm.providers.openai.api_key'),
            'OPENAI_ORG_KEY'       => config('llm.providers.openai.org_key'),
            'ANTHROPIC_API_KEY'    => config('llm.providers.anthropic.api_key'),
            'GEMINI_API_KEY'       => config('llm.providers.gemini.api_key'),
            'CUSTOM_API_KEY'       => config('llm.providers.custom.api_key'),
            'CUSTOM_BASE_URL'      => config('llm.providers.custom.base_url'),
            'SLIDING_WINDOW_SIZE'  => config('llm.sliding_window'),
            'CACHE_PROMPTS'        => config('llm.cache_prompts') ? 'true' : 'false',
            'APP_TIMEZONE'         => config('app.timezone'),
            'SHOW_TOOL_EXECUTION_LOGS' => config('app.show_tool_execution_logs_in_chat', true) ? 'true' : 'false',
        ];
    }

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

        Artisan::call('config:clear');

        $this->mainMenu();
    }

    protected function runSetupWizard()
    {
        $this->info('Let\'s set up your Larabot configuration.');

        $this->setupTelegram();
        $this->setupLLMProvider();
        $this->setupLLMModel();
        $this->otherSettings();
        $this->mainMenu();
    }

    protected function mainMenu(): void
    {
        $this->clearScreen();
        $options = $this->mainMenuOptions();

        while ($selected = $this->choice('What do you want to configure?', $options, 'Exit')) {
            match (true) {
                str_starts_with($selected, 'Telegram Bot') => $this->setupTelegram(),
                str_starts_with($selected, 'LLM Provider') => $this->setupLLMProvider(),
                str_starts_with($selected, 'LLM Model') => $this->setupLLMModel(),
                str_starts_with($selected, 'Other Settings') => $this->otherSettings(),
                default => $this->exit(),
            };

            $options = $this->mainMenuOptions();
            $this->sleep();
            $this->clearScreen();
        }

    }

    protected function mainMenuOptions(): array
    {
        return [
            'Telegram Bot (' . Setting::get('bot_name', 'Larabot') . ')',
            'LLM Provider (' . ucwords($this->env('LLM_PROVIDER', 'custom')) . ')',
            'LLM Model (' . $this->env('LLM_MODEL', 'kimi-k2.5:cloud') . ')',
            'Other Settings',
            'Exit',
        ];
    }

    protected function exit()
    {
        $this->line('Exiting configuration. You can run this command again anytime to change settings.');
        exit(0);
    }

    protected function writeToEnv(string $key, string $value): bool|int
    {
        $this->env[$key] = $value;
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

    protected function env(string $key, mixed $default = null): mixed
    {
        return $this->env[$key] ?? $default;
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

    protected function sleep(?int $seconds = null): int
    {
        return sleep($seconds ?? $this->sleep);
    }

    protected function beautifyProviderName(string $name): string
    {
        return match ($name) {
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            default => 'OpenAI-compatible (ollama)',
        };
    }
}
