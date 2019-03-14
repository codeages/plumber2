<?php
namespace Codeages\Plumber;

class QueueFactory
{
    private $options;

    public function __construct($options = [])
    {
        $this->options = $options;
    }

    public function create($queueName)
    {
        if (!isset($this->options[$queueName])) {
            throw new QueueException("Queue {$queueName} config is not exist.");
        }

        $options = $this->options[$queueName];
        if (!isset($options['type'])) {
            throw new QueueException("Queue {$queueName} config is invalid.");
        }

        switch ($options['type']) {
            case 'redis':
                $queue = new RedisQueue($options);
                break;
            default:
                throw new QueueException("Queue {$queueName} type {$options['type']} is not support.");
                break;
        }

        return $queue;
    }
}