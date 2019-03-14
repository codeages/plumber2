# Plumber


## 安装

```
composer require codeages/plumber
```

## 使用

```
Plumber 2.0.0

Usage:
  bin/plumber (run|start|restart|stop)  [--bootstrap=<file>]

Options:
  -h|--help    show this
  -b <file> --bootstrap=<file>  Load configuration file [default: plumber.php]
```

### 启动
```
bin/plumber start -b bootstrap-file-path   # `bootstrap-file-path`为启动配置文件路径
```

### 重启
```
bin/plumber restart -b bootstrap-file-path
```

### 停止
```
bin/plumber stop -b bootstrap-file-path
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).