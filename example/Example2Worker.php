<?php

namespace Codeages\Plumber\Example;

use Codeages\Plumber\AbstractWorker;
use Codeages\Plumber\Job;

class Example2Worker extends AbstractWorker
{
    public function execute(Job $job)
    {
        $this->logger->info("hello example2 worker....");
//        exec('/bin/sleep 10');
    }
}