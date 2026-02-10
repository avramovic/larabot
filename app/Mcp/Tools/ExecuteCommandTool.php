<?php

namespace App\Mcp\Tools;

use App\Channels\Telegram\Telegram;
use App\Mcp\BaseMcpTool;
use App\Models\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld(true)]
class ExecuteCommandTool extends BaseMcpTool
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
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        $request->validate([
            'command' => 'required|string',
        ]);

        $command = $request->get('command');
        $cwd = $request->get('cwd') ?: base_path();

        $this->chat->sendChatAction();

        try {
            // Use fromShellCommandline to handle quoted arguments correctly
            $process = \Symfony\Component\Process\Process::fromShellCommandline($command, $cwd);
            $process->run();
        } catch (\Exception $e) {
            \Log::error("Failed to execute command: " . $e->getMessage());
            return Response::error("Failed to execute command: " . $e->getMessage());
        }

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
                ->description('REQURIED. Command to run on system ('.php_uname().')')
                ->required(),
            'cwd' => $schema->string()
                ->description('Current working directory, default is: '.base_path())
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
