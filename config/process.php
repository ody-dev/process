<?php

return [
    'max_processes' => 256,
    'enable_coroutine' => true,
    "processes" => [
        // register your on boot processes here
        \App\Processes\SomeProcess::class
    ]
];