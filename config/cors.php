<?php
return [
    'paths'                    => ['api/*', 'mcp/*'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => ['*'],
//    'allowed_origins_patterns' => [
//        '/^https?:\/\/([a-z0-9-]+\.)?annaponsprojects\.com$/',
//    ],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 3600,
    'supports_credentials'     => false,
];
