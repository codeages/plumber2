<?php

namespace Codeages\Plumber;

interface QueueInterface
{
    public function reserveJob(string $tube, $blocking = false);

    public function putJob(string $tube, Job $job);

    public function buryJob(string $tube, Job $job);

    public function finishJob(string $tube, Job $job);

    public function clear(string $tube);

    public function stats();
}