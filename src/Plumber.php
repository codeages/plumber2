<?php
namespace Codeages\Plumber;

use Codeages\Plumber\Queue\QueueFactory;
use Codeages\RateLimiter\RateLimiter;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Swoole\Process;

class Plumber
{
    private $options;

    private $container;

    private $pidFile;

    private $queueFactory;

    private $limiterFactory;

    const ALREADY_RUNNING_ERROR = 1;

    const LOCK_PROCESS_ERROR = 2;

    const WORKER_STATUS_IDLE = 'idle';

    const WORKER_STATUS_BUSY = 'busy';

    const WORKER_STATUS_LIMITED = 'limited';

    const WORKER_STATUS_FAILED = 'failed';


    /**
     * Plumber constructor.
     * @param array $options
     * @param ContainerInterface|null $container
     */
    public function __construct(array $options, ContainerInterface $container = null)
    {
        $this->options = OptionsResolver::resolve($options);
        $this->container = $container;
        $this->pidFile = new PidFile($options['pid_path']);
        $this->queueFactory = new QueueFactory($options['queues']);
        $this->limiterFactory = new RateLimiterFactory($options['rate_limiter']);
    }

    /**
     * @param $op
     * @throws \Exception
     */
    public function main($op)
    {
        switch ($op) {
            case 'run':
                $this->start(false);
                break;
            case 'start':
                $this->start(true);
                break;
            case 'stop':
                $this->stop();
                break;
            case 'restart':
                $this->restart();
                break;
        }
    }


    /**
     * @param bool $daemon
     * @throws \Exception
     */
    private function start($daemon = false)
    {
        if ($this->pidFile->isRunning()) {
            echo "error: plumber is already running(PID: {$this->pidFile->read()}).\n";
            exit(self::ALREADY_RUNNING_ERROR);
        }

        if ($daemon) {
            Process::daemon(true, false);
        }

        if (!$this->pidFile->write(posix_getpid())) {
            echo 'error: lock process error.';
            exit(self::LOCK_PROCESS_ERROR);
        }

        $this->setMasterProcessName();

        $logger = $this->createLogger($daemon);
        ErrorHandler::register($logger);

        $workersOptions = $this->getWorkersOptions();
        $recreateLimiter = $this->createWorkerRecreateLimiter();

        $pool = new Process\Pool(count($workersOptions));
        $pool->on('WorkerStart', function(Process\Pool $pool, $workerId) use ($workersOptions, $logger, $recreateLimiter) {
            $running = true;
            pcntl_signal(SIGTERM, function () use (&$running) {
                $running = false;
            });
            pcntl_signal(SIGINT, function () use (&$running) {
                $running = false;
            });

            $options = $workersOptions[$workerId];

            $remainTimes = $recreateLimiter->getAllow($workerId);
            if ($remainTimes <= 0) {
                $logger->error("[{$this->options['app_name']}] queue `{$options['topic']}` worker #{$workerId} restart failed.");
                $this->setWorkerProcessName($pool, $workerId, $options['topic'], self::WORKER_STATUS_FAILED);

                while (true) {
                    pcntl_signal_dispatch();
                    sleep(2);
                }
            }

            if ($remainTimes < $recreateLimiter->getMaxAllowance()) {
                $logger->info("sleep 1 second.");
                sleep(1);
            }

            $recreateLimiter->check($workerId);

            $this->setWorkerProcessName($pool, $workerId, $options['topic'], self::WORKER_STATUS_IDLE);

            /** @var WorkerInterface $worker */
            $worker = new $options['class'];

            if ($worker instanceof LoggerAwareInterface) {
                $worker->setLogger($logger);
            }

            if ($worker instanceof  ProcessAwareInterface) {
                $worker->setProcess($pool->getProcess());
            }

            if ($worker instanceof  ContainerAwareInterface && $this->container) {
                $worker->setContainer($this->container);
            }

            $consumeLimiter = !empty($options['consume_limiter']) ? $this->limiterFactory->create($options['consume_limiter']) : null;
            if ($consumeLimiter) {
                $consumeLimiter->purge('consume');
            }

            $queue = $this->queueFactory->create($options['queue']);
            $topic = $queue->listenTopic($options['topic']);

            while ($running) {
                if ($consumeLimiter) {
                    $remainTimes = $consumeLimiter->getAllow('consume');
                    if ($remainTimes <= 0) {
                        $logger->notice("Worker '{$options['class']}' consume limited.");
                        $this->setWorkerProcessName($pool, $workerId, $options['topic'], self::WORKER_STATUS_LIMITED);
                        pcntl_signal_dispatch();
                        sleep(2);
                        continue;
                    }
                }

                $job = $topic->reserveJob(true);
                pcntl_signal_dispatch();
                if (is_null($job)) {
                    continue;
                }

                if ($consumeLimiter) {
                    $consumeLimiter->check('consume');
                }

                //@see https://github.com/swoole/swoole-src/issues/183
                try {
                    $this->setWorkerProcessName($pool, $workerId, $options['topic'], self::WORKER_STATUS_BUSY);
                    $code = $worker->execute($job);
                    switch ($code) {
                        case WorkerInterface::FINISH :
                            $topic->finishJob($job);
                            break;
                        case WorkerInterface::BURY :
                            $topic->buryJob($job);
                            break;
                        case WorkerInterface::RETRY :
                            $topic->putJob($job);
                            break;
                        default:
                            throw new PlumberException("Worker execute must return code.");
                    }

                    $this->setWorkerProcessName($pool, $workerId, $options['topic'], self::WORKER_STATUS_IDLE);
                } catch (\Throwable $e) {
                    $logger->error($e);
                    throw $e;
                }
            }
        });

        $pool->on('WorkerStop', function (Process\Pool $pool, $workerId) use ($daemon, $logger) {
            if ($daemon) {
                $logger->info("worker#{$workerId} is stopped.");
            }
        });

        $pool->start();
    }

    /**
     * Stop worker processes.
     */
    private function stop()
    {
        $pid = $this->pidFile->read();
        if (!$pid) {
            echo "plumber is not running.\n";

            return;
        }

        echo 'plumber is stopping...';
        posix_kill($pid, SIGTERM);
        while (1) {
            if (Process::kill($pid, 0)) {
                continue;
            }

            $this->pidFile->destroy();

            echo "[OK]\n";
            break;
        }
    }


    /**
     * @throws \Exception
     */
    private function restart()
    {
        $this->stop();

        sleep(1);

        echo "plumber is starting...[OK]";
        $this->start(true);
    }

    /**
     * @param bool $daemon
     * @return Logger
     * @throws \Exception
     */
    private function createLogger($daemon = true)
    {
        $logger = new Logger('plumber');
        if ($daemon) {
            $logger->pushHandler(new StreamHandler($this->options['log_path']));
        } else {
            $logger->pushHandler(new StreamHandler('php://output'));
        }

        return $logger;
    }

    private function getWorkersOptions()
    {
        $options = [];
        $workerId = 0;
        foreach ($this->options['workers'] as $workerOptions) {
            for ($i = 1; $i <= $workerOptions['num'] ?? 1; $i ++) {
                $options[$workerId] = $workerOptions;
                $workerId ++;
            }
        }

        return $options;
    }

    private function createWorkerRecreateLimiter()
    {
        $name = sprintf(
            'plumber:%s:rate_limiter:worker_recreate',
            $this->options['app_name'] ?? ''
        );

        return new RateLimiter(
            $name,
            10,
            60,
            new SwooleTableRateLimiterStorage()
        );
    }

    private function setWorkerProcessName(Process\Pool $pool, $workerId, $topic, $status)
    {
        $name = sprintf(
            'plumber:%s worker #%s listening %s queue (%s)',
            isset($this->options['app_name']) ? " [{$this->options['app_name']}]" : '',
            $workerId,
            $topic,
            $status
        );

        @$pool->getProcess()->name($name);
    }

    private function setMasterProcessName()
    {
        $name = sprintf(
            'plumber:%s master',
            isset($this->options['app_name']) ? " [{$this->options['app_name']}]" : ''
        );

        @swoole_set_process_name($name);
    }
}