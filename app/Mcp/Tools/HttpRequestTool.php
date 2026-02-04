<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class HttpRequestTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Make HTTP requests to external services.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        //
        $request->validate([
            'url'     => 'required|url',
            'method'  => 'sometimes|string|in:GET,POST,PUT,DELETE,PATCH,HEAD,OPTIONS',
            'headers' => 'sometimes|array',
            'body'    => 'sometimes|array',
            'query'    => 'sometimes|array',
        ]);

        $method = $request->get('method', 'GET');
        $url = $request->get('url');
        $headers = $request->get('headers', []);

        $options = [
            'headers' => $headers,
        ];

        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            if ($body = $request->get('body')) {
                $options['form_params'] = $body;
            }
        }

        if ($query = $request->get('query')) {
            $options['query'] = $query;
        }

        try {

//            return Response::error(json_encode($options, JSON_PRETTY_PRINT));
            $response = Http::withOptions([
                'debug' => false,
                'allow_redirects' => true,
            ])
                ->send($method, $url, $options);
        } catch (\Exception $e) {
            return Response::error('HTTP request failed: ' . $e->getMessage());
        }

        return Response::structured([
            'status'  => $response->status(),
            'headers' => $response->headers(),
            'body'    => $response->body(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->description('The URL to send the HTTP request to')
                ->format('uri')
                ->required(),
            'method' => $schema->string()
                ->description('The HTTP method to use (GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS)')
                ->enum(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])
                ->default('GET'),
            'headers' => $schema->object()
//                ->additionalProperties($schema->string())
                ->description('Optional headers to include in the request'),
            'body' => $schema->object()
                ->description('The body of the request for methods like POST, PUT, DELETE, PATCH'),
            'query' => $schema->object()
                ->description('The query parameters for GET, HEAD, OPTIONS requests'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->integer()
                ->description('HTTP status code'),
            'headers' => $schema->object()
                ->description('Response headers'),
            'body' => $schema->string()
                ->description('Response body'),
        ];
    }
}
