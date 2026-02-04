<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DescribeExecuteCommandPrompt;
use App\Mcp\Tools\ExecuteCommandTool;
use App\Mcp\Tools\HttpRequestTool;
use App\Mcp\Tools\OperatingSystemInfoTool;
use Laravel\Mcp\Server;

class LarabotServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Larabot Server / OS: '  . PHP_OS;

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        - Send a command line instruction to the machine.
        - Get MCP server (usually this machine) operating system info
        - Make HTTP requests
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ExecuteCommandTool::class,
        OperatingSystemInfoTool::class,
        HttpRequestTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
        DescribeExecuteCommandPrompt::class,
    ];
}
