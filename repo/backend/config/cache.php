<?php

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => 'File',
            'path' => '/var/www/html/runtime/cache/',
            'prefix' => 'pantrypilot',
            'expire' => 0,
            'tag_prefix' => 'tag:',
            'serialize' => [],
        ],
    ],
];
