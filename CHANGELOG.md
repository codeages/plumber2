# CHANGELOG

## HEAD (Unreleased)

* 修复：`plumber stop` / `restart` 卡死与进程残留
  * `Plumber::start()` 在 `Process::daemon()` 后主动 `posix_setsid()` 创建独立 session 和进程组，
    修复 daemon 进程与父进程共享进程组导致 `posix_kill(-$pid)` 失败（`ESRCH`）的问题
  * `Plumber::stop()` 重写：用 `pcntl_waitpid` 阻塞等 master 退出（最长 10 分钟），
    让 worker 把当前 job 跑完再退出；超时后才用 SIGKILL 强杀进程组兜底

## 0.4.6 (2019-04-29)

* master 进程名 增加 worker 进程数量的显示，便于监控；
* 修复 undefined index rate_limiter 的错误。

