<?php

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => 'mysql',
            'database' => 'pantrypilot',
            'username' => 'pantry',
            'password' => 'pantrypass',
            'hostport' => '3306',
            'charset' => 'utf8mb4',
            'prefix' => '',
            'debug' => true,
            'break_reconnect' => true,
        ],
    ],
];
