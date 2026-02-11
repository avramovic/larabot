<?php

namespace App\Services;

use App\Adapters\McpToolAdapter;
use App\Enums\LLMProvider;
use App\Mcp\Tools\ExecuteCommandTool;
use App\Mcp\Tools\HttpRequestTool;
use App\Mcp\Tools\ImageSearchTool;
use App\Mcp\Tools\MemoryDeleteTool;
use App\Mcp\Tools\MemorySaveTool;
use App\Mcp\Tools\MemoryGetTool;
use App\Mcp\Tools\MemoryUpdateTool;
use App\Mcp\Tools\OperatingSystemInfoTool;
use App\Mcp\Tools\SchedulerAddTool;
use App\Mcp\Tools\SchedulerDeleteTool;
use App\Mcp\Tools\SchedulerListTool;
use App\Mcp\Tools\SchedulerUpdateTool;
use App\Mcp\Tools\SendFileTool;
use App\Mcp\Tools\WebSearchTool;
use App\Models\Chat;
use App\Models\Message;
use GuzzleHttp\Promise\PromiseInterface;
use Soukicz\Llm\Cache\FileCache;
use Soukicz\Llm\Client\Anthropic\AnthropicClient;
use Soukicz\Llm\Client\Gemini\GeminiClient;
use Soukicz\Llm\Client\LLMAgentClient;
use Soukicz\Llm\Client\LLMClient;
use Soukicz\Llm\Client\ModelInterface;
use Soukicz\Llm\Client\OpenAI\OpenAIClient;
use Soukicz\Llm\Client\OpenAI\OpenAICompatibleClient;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\LLMRequest;
use Soukicz\Llm\LLMResponse;
use Soukicz\Llm\Message\LLMMessage;

class LLMChatService
{
    protected LLMAgentClient $agent;

    public function __construct()
    {
        $this->agent = new LLMAgentClient();
    }

    public static function getConversation(int $sliding_window = -1): LLMConversation
    {
        if ($sliding_window === -1 || empty($sliding_window)) {
            $messages = Message::where('role', '!=', 'system')->orderBy('created_at', 'desc')->get()->reverse();
            $messages->prepend(Message::systemIntroductoryMessage());
        } else {
            $messages = Message::where('role', '!=', 'system')->orderBy('created_at', 'desc')
                ->take($sliding_window)->get()->reverse();
            $messages->prepend(Message::systemIntroductoryMessage());
        }

        $llm_messages = [];
        foreach ($messages as $message) {
            $llm_messages[] = match ($message->role) {
                'user' => LLMMessage::createFromUserString($message->contents),
                'assistant' => LLMMessage::createFromAssistantString($message->contents),
                'system' => LLMMessage::createFromSystemString($message->contents),
                default => throw new \InvalidArgumentException("Invalid message role: {$message->role}"),
            };
        }

        return new LLMConversation($llm_messages);
    }


    public function getModel(?string $model = null): ModelInterface
    {
        $model = $model ?? config('models.default_model');
        $models = config("models.models");
        $model_class = $models[$model]['class'] ?? null;
        $model_version = $models[$model]['model'] ?? null;

        if (empty($model_class)) {
            throw new \InvalidArgumentException("LLM Model class for model '$model' is not defined in configuration");
        }

        if (!class_exists($model_class)) {
            throw new \InvalidArgumentException("LLM Model class for model '$model' does not exist");
        }

        if (empty($model_version)) {
            return new $model_class();
        }

        return new $model_class($model_version);
    }

    public function getClient(?string $provider = null): LLMClient
    {
        $provider = $provider ?? config('llm.default_provider');
        $provider_enum = LLMProvider::tryFrom($provider);

        $cache = null;
        $cache_prompts = config('llm.cache_prompts', false);
        if ($cache_prompts) {
            $cache = new FileCache(sys_get_temp_dir());
        }

        return match ($provider_enum) {
            LLMProvider::ANTHROPIC => new AnthropicClient(config('llm.providers.anthropic.api_key'), $cache),
            LLMProvider::GEMINI => new GeminiClient(config('llm.providers.gemini.api_key'), $cache),
            LLMProvider::OPENAI => new OpenAIClient(config('llm.providers.openai.api_key'),
                config('llm.providers.openai.org_key'), $cache),
            default => new OpenAICompatibleClient(config("llm.providers.$provider.api_key"),
                config("llm.providers.$provider.base_url"), $cache),
        };
    }

    public function getTools(): array
    {
        return [
            // Basic tools
            (new McpToolAdapter(new WebSearchTool()))->toLlmTool(),
            (new McpToolAdapter(new ImageSearchTool()))->toLlmTool(),
            (new McpToolAdapter(new ExecuteCommandTool()))->toLlmTool(),
            (new McpToolAdapter(new HttpRequestTool()))->toLlmTool(),
            (new McpToolAdapter(new SendFileTool()))->toLlmTool(),
            // Scheduler tools
            (new McpToolAdapter(new SchedulerListTool()))->toLlmTool(),
            (new McpToolAdapter(new SchedulerAddTool()))->toLlmTool(),
            (new McpToolAdapter(new SchedulerUpdateTool()))->toLlmTool(),
            (new McpToolAdapter(new SchedulerDeleteTool()))->toLlmTool(),
            // Memories tool
            (new McpToolAdapter(new MemoryGetTool()))->toLlmTool(),
            (new McpToolAdapter(new MemorySaveTool()))->toLlmTool(),
            (new McpToolAdapter(new MemoryUpdateTool()))->toLlmTool(),
            (new McpToolAdapter(new MemoryDeleteTool()))->toLlmTool(),
        ];
    }

    public function send(LLMConversation $conversation): LLMResponse
    {
        return $this->agent->run(
            client: $this->getClient(),
            request: new LLMRequest(
                model: $this->getModel(),
                conversation: $conversation,
                tools: $this->getTools(),
            )
        );
    }

    public function process(LLMResponse $response): LLMMessage
    {
        return LLMMessage::createFromAssistantString($response->getLastText());
    }
}
