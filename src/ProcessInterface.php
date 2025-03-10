<?php
declare(strict_types=1);

namespace Ody\Process;

use Swoole\Process;

/**
 * Interface that all runnable processes must implement
 */
interface ProcessInterface
{
    /**
     * Process constructor
     *
     * @param array $args Arguments for the process
     * @param \Process $worker The Swoole Process instance
     */
    public function __construct(Process $worker);

    /**
     * The main logic of the process
     */
    public function handle(): void;
}
