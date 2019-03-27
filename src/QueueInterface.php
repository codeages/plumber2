<?php

namespace Codeages\Plumber;


interface QueueInterface
{
    public function push(string $tube, Job $job);

    public function pop(string $tube, $blocking = false);

    public function clear(string $tube);

    public function stats();
}