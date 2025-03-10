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

            // Start your process
            Process::execute(\App\Processes\SomeProcess::class, ['param1' => 'value1']);
        }
    }
}