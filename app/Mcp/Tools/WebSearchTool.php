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
        API key can be set in the config/services.php file.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'query' => 'required|string',
        ]);

        $url = 'https://api.search.brave.com/res/v1/web/search';
        $query = $request->get('query');
        $apiKey = config('services.brave_search.api_key');

        if (empty($apiKey)) {
            return Response::error('Brave Search API key is not configured. Get it on https://brave.com/search/api/ and add it to config/services.php');
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
                    'q' => $query,
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
        ];
    }
}
