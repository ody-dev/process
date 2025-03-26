<?php

namespace Ody\Process;

use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Table;

class ProcessManager
{
    /**
     * @var Table|null Shared memory table to track running processes
     */
    private static ?Table $processTable = null;

    /**
     * @var array Configuration for the process manager
     */
    private static array $config = [
        'max_processes' => 128,
        'log_path' => '/tmp/process_manager.log',
        'enable_coroutine' => true,
    ];

    /**
     * Initialize the process manager
     */
    public static function init(array $config = []): void
    {
        // Merge custom config with defaults
        self::$config = array_merge(self::$config, $config);

        // Create shared memory table for process tracking
        self::$processTable = new Table(self::$config['max_processes']);
        self::$processTable->column('pid', Table::TYPE_INT);
        self::$processTable->column('name', Table::TYPE_STRING, 128);
        self::$processTable->column('status', Table::TYPE_INT);
        self::$processTable->column('started_at', Table::TYPE_INT);
        self::$processTable->column('memory', Table::TYPE_INT);
        self::$processTable->column('type', Table::TYPE_STRING, 32);
        self::$processTable->column('metadata', Table::TYPE_STRING, 1024);
        self::$processTable->create();
    }

    /**
     * Execute a process class
     *
     * @param string $processClass Fully qualified class name implementing ProcessInterface
     * @param bool $daemon Run as daemon process
     * @return int Process ID
     * @throws ProcessException
     */
    public static function execute(string $processClass, array $args = [], bool $daemon = false): int
    {
        if (self::$processTable === null) {
            self::init();
        }
        if (!class_exists($processClass)) {
            throw new ProcessException("Process class {$processClass} not found");
        }

        if (!is_subclass_of($processClass, ProcessInterface::class)) {
            throw new ProcessException("Process class {$processClass} must implement ProcessInterface");
        }

        // Get socket type from process class
        $socketType = SOCK_DGRAM; // Default
        if (method_exists($processClass, 'getSocketType')) {
            $socketType = $processClass::getSocketType();
        }

        $process = new Process(function (Process $worker) use ($processClass, $args) {
            if (self::$config['enable_coroutine']) {
                Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

                go(function () use ($processClass, $args, $worker) {
                    $instance = new $processClass($args, $worker);
                    $instance->handle();
                });
            } else {
                $instance = new $processClass($worker);
                $instance->handle();
            }
        }, false, $socketType, self::$config['enable_coroutine']);

        $process->name("ODY:{$processClass}");
        $pid = $process->start();

        if ($pid === false) {
            throw new ProcessException("Failed to start process {$processClass}");
        }

        // Determine process type
        $type = 'standard';
        if (is_subclass_of($processClass, UnixProcess::class)) {
            $type = 'unix';
        } elseif (is_subclass_of($processClass, TcpProcess::class)) {
            $type = 'tcp';
        }

        // Store process info in shared table
        self::$processTable->set((string)$pid, [
            'pid' => $pid,
            'name' => $processClass,
            'status' => 1, // 1 = running
            'started_at' => time(),
            'type' => $type,
            'memory' => 0,
        ]);

        return $pid;
    }

    /**
     * Execute a TCP-based process
     *
     * @param string $processClass Fully qualified class name extending TcpProcess
     * @param array $args Arguments to pass to the process
     * @param bool $daemon Run as daemon process
     * @return array Process information with PID and port
     * @throws \Exception
     */
    public static function executeTcp(string $processClass, array $args = [], bool $daemon = false): array
    {
        if (!is_subclass_of($processClass, TcpProcess::class)) {
            throw new \Exception("Process class {$processClass} must extend TcpProcess");
        }

        // If port is 0, we need to get the assigned port from the process
        $needPort = (!isset($args['port']) || $args['port'] === 0);

        // Create a pipe to read back the port
        $process = new Process(function (Process $worker) use ($processClass, $args) {
            if (self::$config['enable_coroutine']) {
                \Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);
                \Swoole\Coroutine\go(function () use ($processClass, $args, $worker) {
                    $instance = new $processClass($args, $worker);
                    $instance->handle();
                });
            } else {
                $instance = new $processClass($args, $worker);
                $instance->handle();
            }
        }, true, SOCK_STREAM, self::$config['enable_coroutine']);

        $process->name("ODY:{$processClass}");
        $pid = $process->start();

        if ($pid === false) {
            throw new \Exception("Failed to start TCP process {$processClass}");
        }

        // Store process info in shared table
        self::$processTable->set((string)$pid, [
            'pid' => $pid,
            'name' => $processClass,
            'status' => 1, // 1 = running
            'started_at' => time(),
            'memory' => 0,
            'type' => 'tcp',
            'metadata' => json_encode($args),
        ]);

        $result = ['pid' => $pid];

        // If we need to get the assigned port
        if ($needPort) {
            // Wait for port information (with timeout)
            $timeout = microtime(true) + 3; // 3 second timeout
            while (microtime(true) < $timeout) {
                $data = $process->read();
                if ($data) {
                    $info = json_decode($data, true);
                    if (isset($info['port'])) {
                        $result['port'] = $info['port'];

                        // Update metadata
                        $args['port'] = $info['port'];
                        self::$processTable->set((string)$pid, [
                            'metadata' => json_encode($args),
                        ]);

                        break;
                    }
                }
                usleep(10000); // 10ms
            }

            if (!isset($result['port'])) {
                throw new \Exception("Failed to get port from TCP process");
            }
        } else {
            $result['port'] = $args['port'];
        }

        return $result;
    }

    /**
     * Execute a Unix socket-based process
     *
     * @param string $processClass Fully qualified class name extending UnixProcess
     * @param array $args Arguments to pass to the process
     * @param bool $daemon Run as daemon process
     * @return array Process information with PID and socket path
     * @throws \Exception
     */
    public static function executeUnix(string $processClass, array $args = [], bool $daemon = false): array
    {
        if (!is_subclass_of($processClass, UnixProcess::class)) {
            throw new \Exception("Process class {$processClass} must extend UnixProcess");
        }

        // Generate socket path if not provided
        if (!isset($args['socket_path'])) {
            $args['socket_path'] = '/tmp/swoole_' . uniqid() . '.sock';
        }

        $pid = self::execute($processClass, $args, $daemon);

        return [
            'pid' => $pid,
            'socket_path' => $args['socket_path'],
        ];
    }

    /**
     * Kill a process by PID
     */
    public static function kill(int $pid, int $signal = SIGTERM): bool
    {
        if (!self::$processTable->exist((string)$pid)) {
            return false;
        }

        Process::kill($pid, $signal);

        $info = self::$processTable->get((string)$pid);

        // Clean up socket if it's a Unix process
        if ($info['type'] === 'unix') {
            $metadata = json_decode($info['metadata'], true);
            if (isset($metadata['socket_path']) && file_exists($metadata['socket_path'])) {
                @unlink($metadata['socket_path']);
            }
        }

        self::$processTable->del((string)$pid);

        echo "Process killed {$pid}\n";

        return true;
    }

    /**
     * Get all running processes
     */
    public static function getRunningProcesses(): array
    {
        $processes = [];
        foreach (self::$processTable as $pid => $process) {
            $processes[$pid] = $process;
        }

        return $processes;
    }

    /**
     * Check if a process is running by class name
     */
    public static function isRunning(string $processClass): bool
    {
        foreach (self::$processTable as $row) {
            if ($row['name'] === $processClass && $row['status'] === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wait for all processes to finish
     */
    public static function waitAll(): void
    {
        while (Process::wait(false)) {
            // Continue waiting
        }
    }
}
