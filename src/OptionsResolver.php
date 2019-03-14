<?php

namespace Codeages\Plumber;


class OptionsResolver
{
    /**
     * @param array $options
     * @return array
     * @throws QueueException
     */
    public static function resolve(array $options)
    {
        if (!isset($options['workers'])) {
            throw new QueueException("Option 'workers' is missing.");
        }

        if (!is_array($options['workers'])) {
            throw new QueueException("Option 'workers' must be array");
        }

        if (!isset($options['queues'])) {
            throw new QueueException("Option 'queues' is missing.");
        }

        if (!is_array($options['queues'])) {
            throw new QueueException("Option 'queues' must be array");
        }

        if (!isset($options['log_path'])) {
            throw new QueueException("Option 'log_path' is missing.");
        }

        if (!isset($options['pid_path'])) {
            throw new QueueException("Option 'pid_path' is missing.");
        }

        foreach ($options['workers'] as $i => &$workerOptions) {
            if (!isset($workerOptions['class'])) {
                throw new QueueException("Option 'workers[$i].class' is missing.");
            }

            if (!class_exists($workerOptions['class'])) {
                throw new QueueException("Option 'workers[$i].class' {$workerOptions['class']} is not exist.");
            }

            if (!isset($workerOptions['queue'])) {
                throw new QueueException("Option 'workers[$i].queue' is missing.");
            }

            if (!isset($options['queues'][$workerOptions['queue']])) {
                throw new QueueException("Option 'workers[$i].queue' {$workerOptions['queue']} is not exist.");
            }

            if (!isset($workerOptions['tube'])) {
                throw new QueueException("Option 'workers[$i].tube' is missing.");
            }

            if (!is_string($workerOptions['tube'])) {
                throw new QueueException("Option 'workers[$i].tube' value type must be string.");
            }

            if (strlen($workerOptions['tube']) < 1 || strlen($workerOptions['tube']) > 64) {
                throw new QueueException("Option 'workers[$i].tube' value length must be between 1 ~ 64");
            }

            $workerOptions['num'] = $workerOptions['num'] ?? 1;

            if (!is_int($workerOptions['num'])) {
                throw new QueueException("Option 'workers[$i].num' value type must be int.");
            }

            if ($workerOptions['num'] < 1) {
                throw new QueueException("Option 'workers[$i].num' value must be grate than 1.");
            }

            unset($workerOptions);
        }

        foreach ($options['queues'] as $i => $queueOptions) {
            if (!isset($queueOptions['type'])) {
                throw new QueueException("Option 'queues[$i].type' is missing.");
            }

            if (!in_array($queueOptions['type'], ['redis', 'beanstalk'])) {
                throw new QueueException("Option 'queues[$i].type' value must be in [redis, beanstalk].");
            }

            if ($queueOptions['type'] == 'redis') {
                if (!isset($queueOptions['host'])) {
                    throw new QueueException("Option 'queues[$i].host' is missing.");
                }

                if (!isset($queueOptions['port'])) {
                    throw new QueueException("Option 'queues[$i].port' is missing.");
                }
            }
        }

        return $options;
    }

}