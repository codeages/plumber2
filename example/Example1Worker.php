<?php

namespace Codeages\Plumber\Example;

use Codeages\Plumber\AbstractWorker;
use Codeages\Plumber\Job;

class Example1Worker extends AbstractWorker
{
    public function execute(Job $job)
    {
        $this->logger->info("hello example1 worker....");
//        exit(2);
//        var_dump($this->container);
//        exec('/bin/sleep 10');
    }
}