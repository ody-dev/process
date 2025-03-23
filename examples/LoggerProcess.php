<?php

/**
 * Example of a Unix socket process
 */
namespace Ody\Process\Examples;

use Ody\Process\UnixProcess;

class LoggerProcess extends UnixProcess
{
    protected function processMessage(string $data): ?string
    {
        // Log the message
        $logFile = $this->args['log_file'] ?? '/tmp/process.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $data . PHP_EOL, FILE_APPEND);

        return "Logged: " . substr($data, 0, 30) . (strlen($data) > 30 ? '...' : '');
    }
}