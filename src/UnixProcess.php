<?php

namespace Ody\Process;

use Swoole\Process;

/**
 * Process that communicates via Unix domain sockets
 */
class UnixProcess extends BaseProcess
{
    /**
     * @var string Unix socket path
     */
    protected string $socketPath;

    /**
     * @var resource|null Socket resource
     */
    protected $socket = null;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $args, Process $worker)
    {
        parent::__construct($args, $worker);

        $this->socketPath = $args['socket_path'] ?? '/tmp/swoole_' . uniqid() . '.sock';
    }

    /**
     * {@inheritDoc}
     */
    public static function getSocketType(): int
    {
        return SOCK_STREAM; // Stream sockets for Unix domain sockets
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): void
    {
        // Create Unix domain socket server
        $this->createSocketServer();

        // Process loop
        while ($this->running) {
            pcntl_signal_dispatch();

            // Accept and handle connections
            $this->processData();

            usleep(10000); // 10ms
        }

        // Clean up
        $this->cleanup();
    }

    /**
     * Create the Unix domain socket server
     */
    protected function createSocketServer(): void
    {
        // Remove socket file if it exists
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }

        // Create socket
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (!$this->socket) {
            throw new \RuntimeException('Failed to create Unix domain socket');
        }

        // Bind to path
        if (!socket_bind($this->socket, $this->socketPath)) {
            throw new \RuntimeException('Failed to bind to Unix domain socket path');
        }

        // Listen for connections
        if (!socket_listen($this->socket, 128)) {
            throw new \RuntimeException('Failed to listen on Unix domain socket');
        }

        // Set permissions if needed
        chmod($this->socketPath, 0777);

        // Set non-blocking mode
        socket_set_nonblock($this->socket);
    }

    /**
     * Process socket connections
     */
    protected function processData(): void
    {
        // Try to accept a connection
        $clientSocket = @socket_accept($this->socket);

        if ($clientSocket) {
            // Handle the client connection
            $this->handleClient($clientSocket);

            // Close the client socket
            socket_close($clientSocket);
        }
    }

    /**
     * Handle a client connection
     *
     * @param resource $clientSocket Client socket resource
     */
    protected function handleClient($clientSocket): void
    {
        // Read from client
        $data = '';
        while ($buffer = socket_read($clientSocket, 8192)) {
            $data .= $buffer;
        }

        if (!empty($data)) {
            // Process the data
            $result = $this->processMessage($data);

            // Send back result if needed
            if ($result !== null) {
                socket_write($clientSocket, $result, strlen($result));
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

    /**
     * Clean up resources
     */
    protected function cleanup(): void
    {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }

        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }
    }
}