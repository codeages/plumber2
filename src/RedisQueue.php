<?php
namespace Codeages\Plumber;

class RedisQueue implements QueueInterface
{
    /**
     * @var \Redis
     */
    private $redis;

    public function __construct(array $options = [])
    {
        $defaults = [
            'host' => null,
            'port' => 0,
            'timeout' => 1,
            'password' => null,
            'dbindex' => null
        ];
        $options = array_merge($defaults, $options);

        $redis = new \Redis();
        $redis->connect($options['host'], $options['port'], $options['timeout']);

        if ($options['password']) {
            $redis->auth($options['password']);
        }

        if ($options['dbindex']) {
            $redis->select($options['dbindex']);
        }

        $this->redis = $redis;
    }

    /**
     * @param string $tube
     * @param $value
     * @throws QueueException
     */
    public function push(string $tube, $value)
    {
        $pushed = $this->redis->lPush($tube, $value);
        if ($pushed === false) {
            throw new QueueException("Push redis '{$tube}' queue failed.");
        }
    }

    /**
     * @param string $tube
     * @param bool $blocking
     * @param int $timeout
     * @return string|null
     * @throws QueueException
     */
    public function pop(string $tube, $blocking = false, $timeout = 2)
    {
        if ($blocking) {
            $message = $this->redis->brPop($tube, $timeout);
            if (!is_array($message)) {
                throw new QueueException("Pop redis '{$tube}' queue failed.");
            }

            if (empty($message)) {
                return null;
            }

            if (!isset($message[1])) {
                throw new QueueException("Pop redis '{$tube}' queue failed.");
            }

            return $message[1];
        } else {
            $message = $this->redis->rPop($tube);
            if ($message === false) {
                return null;
            }
            return $message;
        }
    }

    public function clear(string $tube)
    {
        $this->redis->del($tube);
    }

    public function stats()
    {
        // TODO: Implement stats() method.
    }
}