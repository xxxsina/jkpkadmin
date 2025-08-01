# Supervisor Worker管理指南

本指南说明如何使用Supervisor来管理健康派卡消息队列Worker进程，替代原有的pcntl_fork方式。

## 为什么使用Supervisor？

### 原有问题
- **pcntl_fork创建的子进程对Supervisor不可见**
- **重复的进程管理**：pcntl_fork和Supervisor都在管理进程
- **信号处理冲突**：可能与Supervisor的信号处理机制产生冲突
- **资源浪费**：创建了不必要的父进程

### Supervisor优势
- **统一进程管理**：所有Worker进程由Supervisor统一管理
- **自动重启**：进程异常退出时自动重启
- **日志管理**：统一的日志收集和轮转
- **状态监控**：实时查看进程状态
- **优雅关闭**：支持优雅关闭和重启

## 安装Supervisor

### macOS
```bash
# 使用Homebrew安装
brew install supervisor

# 启动supervisor服务
brew services start supervisor
```

### Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### CentOS/RHEL
```bash
sudo yum install supervisor
# 或者使用dnf (CentOS 8+)
sudo dnf install supervisor

sudo systemctl enable supervisord
sudo systemctl start supervisord
```

## 配置步骤

### 1. 复制配置文件
```bash
# 复制supervisor配置到系统目录
sudo cp supervisor.conf /etc/supervisor/conf.d/shenhuobao-workers.conf

# macOS用户可能需要使用
sudo cp supervisor.conf /usr/local/etc/supervisor/conf.d/shenhuobao-workers.conf
```

### 2. 修改配置文件
编辑配置文件，修改以下内容：

```ini
# 修改项目路径
command=php /your/actual/path/to/start_workers.php user_operations
directory=/your/actual/path/to/workers

# 修改运行用户
user=your_username

# 修改日志路径（确保目录存在且有写权限）
stdout_logfile=/var/log/supervisor/shenhuobao_user_operations.log
```

### 3. 创建日志目录
```bash
# 创建日志目录
sudo mkdir -p /var/log/supervisor
sudo chown your_username:your_group /var/log/supervisor
```

### 4. 重新加载配置
```bash
# 重新读取配置
sudo supervisorctl reread

# 更新配置
sudo supervisorctl update
```

## 管理Worker进程

### 启动所有Worker
```bash
sudo supervisorctl start shenhuobao:*
```

### 查看状态
```bash
# 查看所有worker状态
sudo supervisorctl status shenhuobao:*

# 查看特定worker状态
sudo supervisorctl status shenhuobao_checkin
```

### 停止Worker
```bash
# 停止所有worker
sudo supervisorctl stop shenhuobao:*

# 停止特定worker
sudo supervisorctl stop shenhuobao_checkin
```

### 重启Worker
```bash
# 重启所有worker
sudo supervisorctl restart shenhuobao:*

# 重启特定worker
sudo supervisorctl restart shenhuobao_checkin
```

### 查看日志
```bash
# 实时查看日志
sudo supervisorctl tail -f shenhuobao_checkin

# 查看最后1000行日志
sudo supervisorctl tail shenhuobao_checkin

# 直接查看日志文件
tail -f /var/log/supervisor/shenhuobao_checkin.log
```

### 发送信号
```bash
# 发送USR1信号查看worker健康状态
sudo supervisorctl signal USR1 shenhuobao_checkin
```

## 代码修改说明

### 主要修改
1. **WorkerManager.php**：移除了pcntl_fork相关代码
2. **start_workers.php**：禁止启动所有worker，只允许启动单个worker
3. **BaseWorker.php**：添加了信号处理和健康检查功能

### 新增功能
- **优雅关闭**：支持SIGTERM和SIGINT信号
- **健康检查**：通过SIGUSR1信号查看worker状态
- **活动监控**：记录处理消息数量和最后活动时间

## 监控和调试

### 检查Worker健康状态
```bash
# 发送USR1信号，worker会在日志中输出状态信息
sudo supervisorctl signal USR1 shenhuobao_checkin

# 然后查看日志
sudo supervisorctl tail shenhuobao_checkin
```

### 常见问题排查

1. **Worker无法启动**
   - 检查PHP路径是否正确
   - 检查项目路径是否正确
   - 检查用户权限
   - 查看错误日志

2. **Worker频繁重启**
   - 检查数据库连接
   - 检查RabbitMQ连接
   - 查看错误日志
   - 检查内存使用情况

3. **日志文件过大**
   - 配置文件中已设置日志轮转
   - 可以手动清理旧日志

### 性能监控
```bash
# 查看所有supervisor进程
ps aux | grep supervisor

# 查看worker进程
ps aux | grep start_workers

# 监控内存使用
top -p $(pgrep -f start_workers | tr '\n' ',')
```

## 生产环境建议

1. **资源限制**：在supervisor配置中添加内存和CPU限制
2. **监控告警**：集成监控系统，当worker异常时发送告警
3. **日志分析**：使用ELK或其他日志分析工具
4. **备份策略**：定期备份配置文件
5. **更新流程**：制定worker更新和重启的标准流程

## 配置文件参数说明

- `autostart`: 随supervisor启动
- `autorestart`: 进程异常退出时自动重启
- `user`: 运行进程的用户
- `numprocs`: 进程数量（可以根据负载调整）
- `redirect_stderr`: 将stderr重定向到stdout
- `stdout_logfile`: 日志文件路径
- `stopwaitsecs`: 等待进程优雅关闭的时间
- `killasgroup`: 杀死整个进程组
- `stopsignal`: 停止信号（TERM用于优雅关闭）

通过以上配置，您的Worker进程将由Supervisor统一管理，获得更好的稳定性和可维护性。