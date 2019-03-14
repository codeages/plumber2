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
}