<?php

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$redis->lPush('test_topic_1', 'message 1');