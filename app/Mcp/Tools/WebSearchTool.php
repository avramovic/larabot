<?php

namespace App\Mcp\Tools;

use App\Mcp\BaseMcpTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class WebSearchTool extends BaseMcpTool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search the interwebz using the Brave search API.
        API key can be set in the config/services.php file or defined as BRAVE_SEARCH_API_KEY environment variable.
        Get your API key on https://brave.com/search/api/
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        $request->validate([
            'query'         => 'required|string',
            'safesearch'    => 'sometimes|string|in:off,moderate,strict',
            'result_filter' => 'sometimes|string|in:web,videos',
            'units'         => 'sometimes|string|in:metric,imperial',
        ]);

        $url = 'https://api.search.brave.com/res/v1/web/search';
        $query = $request->get('query');
        $result_filter = $request->get('result_filter', 'web');
        $apiKey = config('services.brave_search.api_key');

        if (empty($apiKey)) {
            return Response::error('Brave Search API key is not configured. Get it on https://brave.com/search/api/ and add it to config/services.php or define BRAVE_SEARCH_API_KEY environment variable.');
        }

        $this->chat->sendChatAction();

        try {

            $response = Http::withOptions([
                'debug' => false,
                'allow_redirects' => true,
            ])
                ->withHeaders([
                    'X-Subscription-Token' => $apiKey,
                ])
                ->get($url, [
                    'q'             => $query,
                    'safesearch'    => $request->get('safesearch', 'moderate'),
                    'result_filter' => $result_filter,
                    'units'         => $request->get('units', 'metric'),
                ]);
        } catch (\Exception $e) {
            return Response::error('HTTP request failed: ' . $e->getMessage());
        }

        return Response::structured($response->json($result_filter));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('REQUIRED. The search query string')
                ->required(),
            'result_filter' => $schema->string()
                ->enum(['web', 'videos'])
                ->description('Filter results by type (web or videos).')
                ->default('web'),
            'safesearch' => $schema->string()
                ->enum(['off', 'moderate', 'strict'])
                ->description('The safe search level (off, moderate, strict)')
                ->default('moderate'),
            'units' => $schema->string()
                ->enum(['metric', 'imperial'])
                ->description('Units for any measurements in results')
                ->default('metric'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()
                ->description('The search results returned by the Brave Search API'),
        ];
    }
}
