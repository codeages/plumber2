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
     * @param bool $blocking
     * @param int $timeout
     * @return Job|null
     * @throws QueueException
     */
    public function reserveJob(string $tube, $blocking = false, $timeout = 2)
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

            $job = new Job();
            $job->setBody($message[1]);

            return $job;
        } else {
            $message = $this->redis->rPop($tube);
            if ($message === false) {
                return null;
            }

            $job = new Job();
            $job->setBody($message);

            return $job;
        }
    }

    /**
     * @param string $tube
     * @param Job $job
     * @throws QueueException
     */
    public function putJob(string $tube, Job $job)
    {
        $pushed = $this->redis->lPush($tube, $job->getBody());
        if ($pushed === false) {
            throw new QueueException("Push redis '{$tube}' queue failed.");
        }
    }

    public function buryJob(string $tube, Job $job)
    {

    }

    public function finishJob(string $tube, Job $job)
    {

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