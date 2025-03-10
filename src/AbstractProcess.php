<?php

namespace Ody\Process;

class AbstractProcess
{
    private static $name;

    public bool $running = true;

    public function __construct()
    {
        pcntl_signal(SIGTERM, function() use (&$running) {
            // Set flag to false to exit loop gracefully
            $this->running = false;
        });
    }

    public static function getName()
    {
        return static::$name;
    }
}