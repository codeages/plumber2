<?php

namespace Codeages\Plumber\Example;

use Codeages\Plumber\AbstractWorker;
use Codeages\Plumber\Queue\Job;

class Example1Worker extends AbstractWorker
{
    public function execute(Job $job)
    {
        $this->logger->info("hello example1 worker....");

        return self::FINISH;
    }
}