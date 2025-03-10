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
     * @param bool $daemon Run as daemon process
     * @return int Process ID
     * @throws ProcessException
     */
    public static function execute(string $processClass, bool $daemon = false): int
    {
        return ProcessManager::execute($processClass, $daemon);
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