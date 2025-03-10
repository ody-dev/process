<?php

namespace Ody\Process;

/**
 * Standard process with bidirectional pipe communication
 */
class StandardProcess extends BaseProcess
{
    /**
     * {@inheritDoc}
     */
    public static function getSocketType(): int
    {
        return SOCK_DGRAM; // Default to datagram sockets
    }

    /**
     * Process incoming data from the pipe
     */
    protected function processData(): void
    {
        // Non-blocking read
        $data = $this->worker->read(8192);

        if ($data) {
            // Process the data
            $result = $this->processMessage($data);

            // Write back result if needed
            if ($result !== null) {
                $this->worker->write($result);
            }
        }
    }

    /**
     * Process a single message
     * Override this method in child classes
     *
     * @param string $data The data received
     * @return string|null Result to write back, or null if no response
     */
    protected function processMessage(string $data): ?string
    {
        return null;
    }
}