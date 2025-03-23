<?php
namespace Ody\Process\Examples;

use Ody\Process\StandardProcess;

/**
 * Example of a standard process
 */
class EchoProcess extends StandardProcess
{
    public function processMessage(string $data): ?string
    {
        // Simply echo back what we receive
        return "Echo: $data";
    }
}