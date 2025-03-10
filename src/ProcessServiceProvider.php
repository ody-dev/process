<?php

namespace Ody\Process;

use Ody\Core\Foundation\Providers\ServiceProvider;
class ProcessServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            ProcessManager::init([
                'max_processes' => 256,
                'enable_coroutine' => true,
            ]);

            $processes = config('process.processes');
            array_walk($processes, fn ($process) => Process::execute($process));
        }
    }
}