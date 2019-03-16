<?php
namespace Codeages\Plumber;

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

    public function __construct(array $options, ContainerInterface $container = null)
    {
        $this->options = OptionsResolver::resolve($options);
        $this->container = $container;
        $this->pidFile = new PidFile($options['pid_path']);
        $this->queueFactory = new QueueFactory($options['queues']);
        $this->limiterFactory = new RateLimiterFactory($options['rate_limiter']);
    }

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
     * Start worker processes.
     *
     * @param bool $daemon
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

        if (isset($this->options['app_name'])) {
            @swoole_set_process_name(sprintf('plumber: [%s] master', $this->options['app_name']));
        } else {
            @swoole_set_process_name('plumber: master');
        }

        $logger = $this->createLogger($daemon);
        ErrorHandler::register($logger);

        $workersOptions = $this->getWorkersOptions();
        $recreateLimiter = $this->createWorkerRecreateLimitor();

        $pool = new Process\Pool(count($workersOptions));
        $pool->on('WorkerStart', function($pool, $workerId) use ($workersOptions, $logger, $recreateLimiter) {
            $running = true;
            pcntl_signal(SIGTERM, function () use (&$running) {
                $running = false;
            });
            pcntl_signal(SIGINT, function () use (&$running) {
                $running = false;
            });

            $process = $pool->getProcess();
            $options = $workersOptions[$workerId];

            $remainTimes = $recreateLimiter->getAllow($workerId);
            if ($remainTimes <= 0) {
                $logger->error("[{$this->options['app_name']}] queue `{$options['tube']}` worker #{$workerId} restart failed.");
                if (isset($this->options['app_name'])) {
                    @$process->name("plumber: [{$this->options['app_name']}] queue `{$options['tube']}` worker - stoped");
                } else {
                    @$process->name("plumber: queue `{$options['tube']}` worker - stoped");
                }

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

            if (isset($this->options['app_name'])) {
                @$process->name("plumber: [{$this->options['app_name']}] queue `{$options['tube']}` worker - running");
            } else {
                @$process->name("plumber: queue `{$options['tube']}` worker - running");
            }

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
            while ($running) {
                if ($consumeLimiter) {
                    $remainTimes = $consumeLimiter->getAllow('consume');
                    if ($remainTimes <= 0) {
                        $logger->notice("Worker '{$options['class']}' consume limited.");
                        pcntl_signal_dispatch();
                        sleep(2);
                        continue;
                    }
                }

                $message = $queue->pop($options['tube'], true);
                pcntl_signal_dispatch();
                if (is_null($message)) {
                    continue;
                }

                if ($consumeLimiter) {
                    $consumeLimiter->check('consume');
                }

                $job = new Job();
                $job->setBody($message);

                //@see https://github.com/swoole/swoole-src/issues/183
                try {
                    $worker->execute($job);
                } catch (\Throwable $e) {
                    $logger->error($e);
                }
            }
        });

        $pool->on('WorkerStop', function ($pool, $workerId) use ($daemon, $logger) {
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

        echo 'plumber is stoping...';
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
     * Reset worker processes.
     */
    private function restart()
    {
        $this->stop();

        sleep(1);

        echo "plumber is starting...[OK]";
        $this->start(true);
    }

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

    private function createWorkerRecreateLimitor()
    {
        return new RateLimiter(
            'plumber:rate_limiter:worker_recreate',
            10,
            60,
            new SwooleTableRateLimiterStorage()
        );
    }
}