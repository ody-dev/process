<?php

namespace Ody\Process;

/**
 * Facade for the ProcessManager to provide a cleaner API
 */
class Process
{
    /**
     * Execute a process
     *
     * @param string $processClass The fully qualified class name
     * @param array $args Arguments to pass to the process
     * @param bool $daemon Run as daemon process
     * @return int Process ID
     */
    public static function execute(string $processClass, array $args = [], bool $daemon = false): int
    {
        return ProcessManager::execute($processClass, $args, $daemon);
    }

    /**
     * Kill a running process
     */
    public static function kill(int $pid): bool
    {
        return ProcessManager::kill($pid);
    }

    /**
     * Check if a process is running
     */
    public static function isRunning(string $processClass): bool
    {
        return ProcessManager::isRunning($processClass);
    }
}