<?php

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

// Add your custom helper functions below

