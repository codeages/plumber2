<?php

$options = [
    'app_name' => 'ExampleWorker',
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
        ],
        'worker_recreate' => [ // 这个配置必须存在，用于限制 Worker 进程被重新创建的频率，以避免Worker程序异常退出以及消息队列出问题时导致进程不断的退出创建的问题。
            'storage' => 'redis',
            'redis' => ['host' => '127.0.0.1', 'port' => '6379'],
            'allowance' => 10,
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

class ExampleContainer implements \Psr\Container\ContainerInterface
{
    public function get($id)
    {
        // TODO: Implement get() method.
    }

    public function has($id)
    {
        // TODO: Implement has() method.
    }
}

return [
    'options' => $options,
    'container' => new ExampleContainer(),
];