<?php

return [
    'truncate_tables' => false,
    'sort' => 'desc',
    'route_name' => 'debugger_index', // and url
    'is_enabled' => true,
    'log_path' => storage_path('logs/debug.log'),
    'storage_type' => 'database', //database , cache


    'cache' => [
        'key_prefix' => 'debugger:',
        'counter_key' => 'debugger:counter',
        'index_key' => 'debugger:index',
        'files_key' => 'debugger:files',
        'ttl' => 3600, // 1 hour
    ],
];
