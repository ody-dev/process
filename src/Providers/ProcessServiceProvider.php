<?php

namespace Ody\Process\Providers;

use Ody\Foundation\Providers\ServiceProvider;
use Ody\Process\Process;
use Ody\Process\ProcessManager;

class ProcessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if (!$this->isRunningInConsole()) {
            ProcessManager::init([
                'max_processes' => 256,
                'enable_coroutine' => true,
            ]);

            // Start standard processes
            $standardProcesses = config('process.processes', []);
            foreach ($standardProcesses as $process => $args) {
                if (is_numeric($process)) {
                    Process::execute($args, []);
                } else {
                    Process::execute($process, $args);
                }
            }

            // Start TCP processes
            $tcpProcesses = config('process.tcp_processes', []);
            foreach ($tcpProcesses as $process => $args) {
                if (is_numeric($process)) {
                    ProcessManager::executeTcp($args);
                } else {
                    ProcessManager::executeTcp($process, $args);
                }
            }

            // Start Unix socket processes
            $unixProcesses = config('process.unix_processes', []);
            foreach ($unixProcesses as $process => $args) {
                if (is_numeric($process)) {
                    ProcessManager::executeUnix($args);
                } else {
                    ProcessManager::executeUnix($process, $args);
                }
            }
        }
    }
}