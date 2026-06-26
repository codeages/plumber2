# CHANGELOG

## HEAD (Unreleased)

* 修复：`plumber stop` / `restart` 卡死与进程残留
  * `Plumber::start()` 在 `Process::daemon()` 后主动 `posix_setsid()` 创建独立 session 和进程组，
    修复 daemon 进程与父进程共享进程组导致 `posix_kill(-$pid)` 失败（`ESRCH`）的问题
  * `Plumber::stop()` 重写：用 `posix_kill(-$pid, SIGTERM)` 给整个进程组发信号，让 worker 跑完当前 job 再退出；
    用 `posix_kill($pid, 0)` 轮询 master 存活状态，最长 10 分钟；超时后打印 PID 让运维手动 `kill -9`（不自动强杀，避免误杀正在跑的长 job）
  * 跨用户跑 stop 时显式拒绝：`Plumber::stop()` 检测 `posix_kill` EPERM（jason 杀 root master）后
    立即 `[FAIL]` 退出并保留 pidFile，避免假 `[OK]` 把 pidFile 销毁后进入"进程在但无法再 stop"的僵尸状态
  * `Plumber::stop()` 改用 `isProcessAlive()` 区分 EPERM（进程在但无权限）vs ESRCH（进程真不在），
    防止跨用户 stop 误判 ESRCH 提前 `[OK]`
  * **注意**：不能用 `pcntl_waitpid` 等 master 退出——stop 进程不是 daemon 化的 master 的父进程，
    `waitpid` 不能跨进程树 wait，会永远返回 `-1`/`ECHILD`，导致 stop 等满 10 分钟才超时
  * PHP 5.6 的 POSIX 扩展没导出 `EPERM` 常量，统一用 errno 数值 1（POSIX 标准）

## 0.4.6 (2019-04-29)

* master 进程名 增加 worker 进程数量的显示，便于监控；
* 修复 undefined index rate_limiter 的错误。

