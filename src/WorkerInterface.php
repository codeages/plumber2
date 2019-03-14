<?php

namespace Codeages\Plumber;

interface WorkerInterface
{
    public function execute(Job $job);
}