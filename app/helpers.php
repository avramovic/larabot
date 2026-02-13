<?php

use SoloTerm\Solo\Commands\Command;

if (!function_exists('home_dir')) {
    function home_dir(string $path = ''): string
    {
        // Linux / macOS
        if ($home = getenv('HOME')) {
            return $home . normalize_path($path);
        }

        // Windows
        if ($homeDrive = getenv('HOMEDRIVE')) {
            return $homeDrive . getenv('HOMEPATH') . normalize_path($path);
        }

        if ($userProfile = getenv('USERPROFILE')) {
            return $userProfile . normalize_path($path);
        }

        return base_path(normalize_path($path));
    }
}

if (!function_exists('normalize_path')) {
    function normalize_path(string $path): string
    {
        // If on Windows, replace forward slashes with backslashes
        if (DIRECTORY_SEPARATOR === '\\') {
            return DIRECTORY_SEPARATOR . ltrim(str_replace('/', '\\', $path), DIRECTORY_SEPARATOR);
        }

        // On Unix-like systems, return as-is
        return DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('array_insert_after_key')) {
    function array_insert_after_key(array $array, string $afterKey, array $insert): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $value;

            if ($key === $afterKey) {
                foreach ($insert as $k => $v) {
                    $result[$k] = $v;
                }
            }
        }

        return $result;
    }
}

if (!function_exists('larabot_dynamic_queue_workers')) {
    function larabot_dynamic_queue_workers($queue_processes): array
    {
        $queue_work = Command::from('php artisan queue:work');
        if (config('queue.default') === 'sync') {
            $queue_work = $queue_work->lazy();
        }

        $queue_commands = [];
        for ($i = 0; $i < $queue_processes; $i++) {
            $proc_name = 'Queue' . ($queue_processes > 1 ? " ".$i+1 : '');
            $queue_commands[$proc_name] = ($i == 0) ? $queue_work : clone $queue_work;
        }

        return $queue_commands;
    }
}
