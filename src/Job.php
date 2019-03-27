<?php
namespace Codeages\Plumber;

class Job
{
    /**
     * Job ID
     *
     * @var integer
     */
    private $id;

    /**
     * Job Body
     *
     * @var mixed
     */
    private $body;

    /**
     * @var integer
     */
    private $priority;

    /**
     * @var integer
     */
    private $delay;

    /**
     * @var integer
     */
    private $ttr;

    /**
     * Job retry execute times
     *
     * @var integer
     */
    private $retryTimes = 0;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return int
     */
    public function getRetryTimes()
    {
        return $this->retryTimes;
    }

    /**
     * @param int $retryTimes
     */
    public function setRetryTimes($retryTimes)
    {
        $this->retryTimes = $retryTimes;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * @param int $delay
     */
    public function setDelay(int $delay): void
    {
        $this->delay = $delay;
    }

    /**
     * @return int
     */
    public function getTtr(): int
    {
        return $this->ttr;
    }

    /**
     * @param int $ttr
     */
    public function setTtr(int $ttr): void
    {
        $this->ttr = $ttr;
    }
}