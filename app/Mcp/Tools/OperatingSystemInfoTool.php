<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld(true)]
class OperatingSystemInfoTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get info about operating system the MCP is running on.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        return Response::structured([
            'PHP_OS'      => PHP_OS,
            'PHP_VERSION' => PHP_VERSION,
            'uname'       => php_uname(),
            'cwd'         => getcwd(),
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
            // No input parameters
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'PHP_OS' => $schema->string()
                ->description('Operating system PHP is running on'),

            'PHP_VERSION' => $schema->string()
                ->description('PHP version'),

            'uname' => $schema->string()
                ->description('uname information about the operating system'),

            'cwd' => $schema->string()
                ->description('Get current working directory'),
        ];
    }
}
