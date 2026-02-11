<?php

namespace App\Mcp\Tools;

use App\Mcp\BaseMcpTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class ImageSearchTool extends BaseMcpTool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search the interwebz for images using the Brave search API.
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
            'safesearch' => 'sometimes|string|in:off,strict',
        ]);

        $url = 'https://api.search.brave.com/res/v1/images/search';
        $query = $request->get('query');
        $apiKey = config('services.brave_search.api_key');

        if (empty($apiKey)) {
            return Response::error('Brave Search API key is not configured. Get it on https://brave.com/search/api/ and add it to config/services.php or define BRAVE_SEARCH_API_KEY environment variable.');
        }

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
                    'safesearch'    => $request->get('safesearch', 'strict'),
                ]);
        } catch (\Exception $e) {
            return Response::error('HTTP request failed: ' . $e->getMessage());
        }

        $result = $response->json();

        $results = array_map(function ($item) {
            return [
                'title' => Arr::get($item, 'title'),
                'image_url' => Arr::get($item, 'properties.url'),
                'thumbnail' => [
                    'url' => Arr::get($item, 'thumbnail.src'),
                    'alt' => Arr::get($item, 'properties.placeholder'),
                ],
                'confidence' => Arr::get($item, 'confidence'),
            ];
        }, $result['results']);

        return Response::structured(['results' => $results]);
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
            'safesearch' => $schema->string()
                ->enum(['off', 'strict'])
                ->description('The safe search level (off, strict)')
                ->default('strict'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()
                ->description('The search results returned by the Brave Image Search API'),
        ];
    }
}
