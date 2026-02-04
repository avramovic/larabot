<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class WebSearchTool extends Tool
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
        $request->validate([
            'query' => 'required|string',
            'safesearch' => 'sometimes|string|in:off,moderate,strict',
            'result_filter' => 'sometimes|string|in:web,news,videos,strict,locations,query,summarizer,infobox,faq,discussions,all',
            'units' => 'sometimes|string|in:metric,imperial',
        ]);

        $url = 'https://api.search.brave.com/res/v1/web/search';
        $query = $request->get('query');
        $apiKey = config('services.brave_search.api_key');

        if (empty($apiKey)) {
            return Response::error('Brave Search API key is not configured. Get it on https://brave.com/search/api/ and add it to config/services.php or define BRAVE_SEARCH_API_KEY environment variable.');
        }

        try {
            $result_filter = $request->get('result_filter', 'web');
            if ($result_filter === 'all') {
                $result_filter = null;
            }

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

        return Response::structured($response->json());
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
                ->description('The search query string')
                ->required(),
            'result_filter' => $schema->string()
                ->enum(['web', 'news', 'videos', 'strict', 'locations', 'query', 'summarizer', 'infobox', 'faq', 'discussions', 'all'])
                ->description('Filter results by type (e.g., web, news, videos, locations)')
                ->default('web'),
            'safesearch' => $schema->string()
                ->enum(['off', 'moderate', 'strict'])
                ->description('The safesearch level (off, moderate, strict)')
                ->default('moderate'),
            'units' => $schema->string()
                ->enum(['metric', 'imperial'])
                ->description('Units for any measurements in results')
                ->default('metric'),
        ];
    }
}
