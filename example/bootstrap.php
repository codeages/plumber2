<?php

$options = [
    'queues' => [
        'default' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => '6379'
        ]
    ],
    'rate_limiter' => [
        'default' => [
            'storage' => 'redis',
            'redis' => ['host' => '127.0.0.1', 'port' => '6379'],
            'allowance' => 5, // 限制每60秒最多消费1000个
            'period' => 60,
        ]
    ],
    'workers' => [
        [
            'class' => 'Codeages\Plumber\Example\Example1Worker',
            'num' => 2,
            'queue' => 'default',
            'tube' => 'test_tube_1',
            'consume_limiter' => 'default',
        ],
        [
            'class' => 'Codeages\Plumber\Example\Example2Worker',
            'num' => 2,
            'queue' => 'default',
            'tube' => 'test_tube_2',
        ]
    ],
    'log_path' => __DIR__ . '/plumber.log',
    'pid_path' => __DIR__ . '/plumber.pid',

];

return [
    'options' => $options,
    'container' => null
];