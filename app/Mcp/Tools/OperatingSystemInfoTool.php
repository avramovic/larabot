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
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        return Response::structured([
            'PHP_VERSION' => PHP_VERSION,
            'uname'       => php_uname(),
            'cwd'         => base_path(),
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
//            'dummy' => $schema->string()
//                ->description('No input parameters, this is just a placeholder to satisfy the schema requirement')
//                ->default('x'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'PHP_VERSION' => $schema->string()
                ->description('PHP version'),
            'uname' => $schema->string()
                ->description('uname information about the operating system'),
            'cwd' => $schema->string()
                ->description('Get current working directory'),
        ];
    }
}
