<?php

namespace Codeages\Plumber;


interface QueueInterface
{
    public function push(string $tube, $raw);

    public function pop(string $tube, $blocking = false);

    public function clear(string $tube);

    public function stats();
}