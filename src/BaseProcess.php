<?php

namespace Ody\Process;

use Swoole\Process as SwooleProcess;

/**
 * Base Process class that other specialized process types extend
 */
abstract class BaseProcess implements ProcessInterface
{
    /**
     * @var array Arguments passed to the process
     */
    protected array $args;

    /**
     * @var SwooleProcess The Swoole process instance
     */
    protected SwooleProcess $worker;

    /**
     * @var bool Flag to track if the process should continue running
     */
    protected bool $running = true;

    /**
     * Constructor
     *
     * @param array $args Arguments for the process
     * @param SwooleProcess $worker The Swoole process instance
     */
    public function __construct(array $args, SwooleProcess $worker)
    {
        $this->args = $args;
        $this->worker = $worker;

        // Set up signal handlers
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
    }

    /**
     * Signal handler for graceful shutdown
     */
    public function shutdown()
    {
        $this->running = false;
    }

    /**
     * Get the communication channel type
     *
     * @return string Communication channel type
     */
    abstract public static function getSocketType(): int;

    /**
     * Default implementation of handle
     * Child classes should override this
     */
    public function handle(): void
    {
        while ($this->running) {
            // Process signals
            pcntl_signal_dispatch();

            // Process data
            $this->processData();

            // Avoid CPU hogging
            usleep(10000); // 10ms
        }
    }

    /**
     * Process incoming data on the channel
     * Child classes should implement this
     */
    abstract protected function processData(): void;
}