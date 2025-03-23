<?php

return [
    'max_processes' => 256,
    'enable_coroutine' => true,
    "processes" => [
        // List your processes here
        \App\Processes\TestProcess::class,
//        \App\Processes\BackgroundWorker::class
    ]
];