<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class LarabotRunCommand extends Command
{
    protected $signature = 'larabot:run';
    protected $description = 'Start and supervise all Larabot processes';
    protected $processes = [];

    public function handle()
    {
        // Signal handler za Linux/Mac
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }

        $this->info('Starting Larabot supervisor... (Ctrl+C to stop)');

        $configs = [
            ['name' => 'web', 'command' => ['php', 'artisan', 'serve']],
            ['name' => 'queue', 'command' => ['php', 'artisan', 'queue:work']],
            ['name' => 'telegram', 'command' => ['php', 'artisan', 'telegram:listen', '--daemon']],
        ];

        foreach ($configs as $config) {
            $process = new Process($config['command']);
            $process->setTimeout(null);
            $process->setTty(false);
            $process->start();

            $this->processes[$config['name']] = $process;
            $this->info("✓ Started {$config['name']}");
        }

        // Monitoring loop
        try {
            while (true) {
                foreach ($this->processes as $name => $process) {

                    $output = $process->getIncrementalOutput();
                    $errorOutput = $process->getIncrementalErrorOutput();

                    $his = date('H:i:s');
                    if ($output) {
                        $this->line("[$name] [$his] $output");
                    }
                    if ($errorOutput) {
                        $this->error("[$name] [$his] $errorOutput");
                    }

                    if (!$process->isRunning()) {
                        $this->error("✗ Process $name died, restarting...");
                        $process->restart();
                    }
                }
                sleep(1);
            }
        } catch (\Exception $e) {
            $this->shutdown();
        }
    }

    public function shutdown()
    {
        $this->info("\nShutting down remaining processes...");

        foreach ($this->processes as $name => $process) {
            if ($process->isRunning()) {
                $this->info("Stopping $name...");
                $pid = $process->getPid();

                if (PHP_OS_FAMILY === 'Windows') {
                    exec("taskkill /PID $pid /T /F 2>nul");
                } else {
                    $process->stop(3, SIGTERM);
                    if ($process->isRunning()) {
                        exec("kill -9 $pid 2>/dev/null");
                    }
                }
            }
        }

        $this->info('All processes stopped.');
        exit(0);
    }
}
