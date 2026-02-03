<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld(true)]
class ExecuteCommandTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Execute cli commands on server.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'command' => 'required|string',
        ]);
        $command = $request->get('command');
        $cwd = $request->get('cwd') ?: getcwd();

        // Use fromShellCommandline to handle quoted arguments correctly
        $process = \Symfony\Component\Process\Process::fromShellCommandline($command, $cwd);
        $process->run();

        $output = $process->getOutput();
        $errors = $process->getErrorOutput();
        $exitCode = $process->getExitCode();

        if ($exitCode !== 0) {
            return Response::error($errors);
        }

        return Response::make(Response::text($output))
            ->withStructuredContent([
                'output'    => $output,
                'errors'    => $errors,
                'exit_code' => $exitCode,
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
            'command' => $schema->string()
                ->description('Command to run')
                ->required(),
            'cwd' => $schema->string()
                ->description('Current working directory')
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'output' => $schema->string()
                ->description('Output of the command (stdout)'),

            'errors' => $schema->string()
                ->description('Errors of the command (stderr)'),

            'exit_code' => $schema->integer()
                ->description('Exit code of the command'),
        ];
    }
}
