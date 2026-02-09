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
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        $request->validate([
            'url'     => 'required|url',
            'method'  => 'sometimes|string|in:GET,POST,PUT,DELETE,PATCH,HEAD,OPTIONS',
            'headers' => 'sometimes|array',
            'body'    => 'sometimes|array',
            'query'   => 'sometimes|array',
        ]);

        $method = $request->get('method', 'GET');
        $url = $request->get('url');
        $headers = $request->get('headers', []);
        $headers = array_merge([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept'     => '*/*',
        ], $headers);

        $options = [
            'headers' => $headers,
        ];

        $isJson = false;
        if (isset($headers['Content-Type']) && str_contains($headers['Content-Type'], 'application/json')) {
            $isJson = true;
        }

        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            if ($body = $request->get('body')) {
                if ($isJson) {
                    // Accept array or string for JSON body
                    $options['json'] = is_string($body) ? json_decode($body, true) : $body;
                } else {
                    $options['form_params'] = $body;
                }
            }
        }

        if ($query = $request->get('query')) {
            $options['query'] = $query;
        }

        try {
            $response = Http::withOptions([
                'debug'           => false,
                'allow_redirects' => true,
            ])
                ->send($method, $url, $options);
        } catch (\Exception $e) {
            return Response::error('HTTP request failed: ' . $e->getMessage());
        }

        $contentType = $response->header('Content-Type');
        $body = $response->body();

        // Handle binary responses (images, pdf, etc.)
        if (!str_starts_with($contentType, 'text/') && !str_contains($contentType,
                'json') && !str_contains($contentType, 'xml')) {
            $bodyBase64 = base64_encode($body);

            return Response::structured([
                'status'       => $response->status(),
                'headers'      => $response->headers(),
                'body_base64'  => $bodyBase64,
                'content_type' => $contentType,
            ]);
        }

        return Response::structured([
            'status'       => $response->status(),
            'headers'      => $response->headers(),
            'body'         => $body,
            'content_type' => $contentType,
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
            'url'     => $schema->string()
                ->description('The URL to send the HTTP request to')
                ->format('uri')
                ->required(),
            'method'  => $schema->string()
                ->description('The HTTP method to use (GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS). Default: GET')
                ->enum(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])
                ->default('GET'),
            'headers' => $schema->object()
                ->description('Optional headers to include in the request'),
            'body'    => $schema->object()
                ->description('The body of the request for methods like POST, PUT, DELETE, PATCH'),
            'query'   => $schema->object()
                ->description('The query parameters for GET, HEAD, OPTIONS requests'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status'       => $schema->integer()
                ->description('HTTP status code'),
            'headers'      => $schema->object()
                ->description('Response headers'),
            'body'         => $schema->string()
                ->description('Response body (if textual)'),
            'body_base64'  => $schema->string()
                ->description('Base64-encoded response body (if binary)'),
            'content_type' => $schema->string()
                ->description('Response Content-Type header'),
        ];
    }
}
