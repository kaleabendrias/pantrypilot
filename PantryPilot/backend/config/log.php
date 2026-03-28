<?php

return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'type' => 'File',
            'path' => '/var/www/html/runtime/log',
            'single' => false,
            'apart_level' => [],
            'max_files' => 30,
            'json' => false,
            'format' => '[%s][%s] %s',
        ],
    ],
];
