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
use App\Mcp\Tools\NotifyUserTool;
use App\Mcp\Tools\OperatingSystemInfoTool;
use App\Mcp\Tools\SchedulerAddTool;
use App\Mcp\Tools\SchedulerDeleteTool;
use App\Mcp\Tools\SchedulerGetTool;
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
        } else {
            $messages = Message::where('role', '!=', 'system')->orderBy('created_at', 'desc')
                ->take($sliding_window)->get()->reverse();
        }

        $messages->prepend(Message::systemIntroductoryMessage());

        $llm_messages = [];
        /** @var Message $message */
        foreach ($messages as $message) {
            $created_at = $message?->created_at ?? now();
            $format = now()->format('Y-m-d') !== $created_at->format('Y-m-d') ? '[Y-m-d H:i:s]' : '[H:i:s]';
            $timestamp = $created_at->format($format);

            $llm_messages[] = match ($message->role) {
                'user' => LLMMessage::createFromUserString($timestamp . ' ' . $message->contents),
                'assistant' => LLMMessage::createFromAssistantString($message->contents),
                'system' => LLMMessage::createFromSystemString($message->contents),
                default => throw new \InvalidArgumentException("Invalid message role: {$message->role}"),
            };
        }

        return new LLMConversation($llm_messages);
    }


    public function getModel(?string $model = null): ModelInterface
    {
        $provider = $model ?? config('llm.default_provider');
        $model = $model ?? config('models.default_model');
        $models = config("models.models");
        $lookup = ($provider == 'custom') ? $provider : $model;
        $model_class = $models[$lookup]['class'] ?? null;
        $model_version = $models[$lookup]['model'] ?? null;

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

    public function getTools(bool $tool_execution_session = false): array
    {
        $tools = [
            // Basic tools
            (new McpToolAdapter(new WebSearchTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new ImageSearchTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new ExecuteCommandTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new HttpRequestTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new SendFileTool()))->toLlmTool(!$tool_execution_session),
            // Scheduler tools
            (new McpToolAdapter(new SchedulerGetTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new SchedulerAddTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new SchedulerUpdateTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new SchedulerDeleteTool()))->toLlmTool(!$tool_execution_session),
            // Memories tool
            (new McpToolAdapter(new MemoryGetTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new MemorySaveTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new MemoryUpdateTool()))->toLlmTool(!$tool_execution_session),
            (new McpToolAdapter(new MemoryDeleteTool()))->toLlmTool(!$tool_execution_session),
        ];

        if ($tool_execution_session) {
            $tool_execution_tools = [
                (new McpToolAdapter(new NotifyUserTool()))->toLlmTool(),
            ];

            $tools = array_merge($tool_execution_tools, $tools);
        }

        return $tools;
    }

    public function send(LLMConversation $conversation, bool $tool_execution_session = false): LLMResponse
    {
        return $this->agent->run(
            client: $this->getClient(),
            request: new LLMRequest(
                model: $this->getModel(),
                conversation: $conversation,
                tools: $this->getTools($tool_execution_session),
            )
        );
    }

    public function process(LLMResponse $response): LLMMessage
    {
        return LLMMessage::createFromAssistantString($response->getLastText());
    }
}
