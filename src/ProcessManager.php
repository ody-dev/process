<?php

namespace Ody\Process;

use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Table;

class ProcessManager
{
    /**
     * @var Table Shared memory table to track running processes
     */
    private static Table $processTable;

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
        self::$processTable->create();
    }

    /**
     * Execute a process class
     *
     * @param string $processClass Fully qualified class name implementing ProcessInterface
     * @param array $args Arguments to pass to the process
     * @param bool $daemon Run as daemon process
     * @return int Process ID
     * @throws ProcessException
     */
    public static function execute(string $processClass, array $args = [], bool $daemon = false): int
    {
        if (!class_exists($processClass)) {
            throw new ProcessException("Process class {$processClass} not found");
        }

        if (!is_subclass_of($processClass, ProcessInterface::class)) {
            throw new ProcessException("Process class {$processClass} must implement ProcessInterface");
        }

        $process = new Process(function (Process $worker) use ($processClass, $args) {
            if (self::$config['enable_coroutine']) {
                Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

                go(function () use ($processClass, $args, $worker) {
                    var_dump("cid " . Coroutine::getCid());
                    $instance = new $processClass($args, $worker);
                    $instance->handle();
                });
            } else {
                $instance = new $processClass($args, $worker);
                $instance->handle();
            }
        }, false, SOCK_DGRAM, self::$config['enable_coroutine']);

        $process->name("YourFramework: {$processClass}");
        $pid = $process->start();

        if ($pid === false) {
            throw new ProcessException("Failed to start process {$processClass}");
        }

        var_dump([
            'pid' => $pid,
            'name' => $processClass,
            'status' => 1, // 1 = running
            'started_at' => time(),
            'memory' => 0,
        ]);

        // Store process info in shared table
        self::$processTable->set((string)$pid, [
            'pid' => $pid,
            'name' => $processClass,
            'status' => 1, // 1 = running
            'started_at' => time(),
            'memory' => 0,
        ]);

        return $pid;
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
