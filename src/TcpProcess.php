<?php

namespace Ody\Process;

use Swoole\Process;

/**
 * Process that communicates via TCP sockets
 */
class TcpProcess extends BaseProcess
{
    /**
     * @var string Host to bind to
     */
    protected string $host;

    /**
     * @var int Port to bind to
     */
    protected int $port;

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

        $this->host = $args['host'] ?? '127.0.0.1';
        $this->port = $args['port'] ?? 0; // 0 means OS will assign a port
    }

    /**
     * {@inheritDoc}
     */
    public static function getSocketType(): int
    {
        return SOCK_STREAM; // Stream sockets for TCP
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): void
    {
        // Create TCP socket server
        $this->createSocketServer();

        // Get the assigned port if it was 0
        if ($this->port === 0) {
            socket_getsockname($this->socket, $addr, $this->port);
            $this->worker->write(json_encode(['port' => $this->port]));
        }

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
     * Create the TCP socket server
     */
    protected function createSocketServer(): void
    {
        // Create socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$this->socket) {
            throw new \RuntimeException('Failed to create TCP socket');
        }

        // Set SO_REUSEADDR option
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        // Bind to address and port
        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new \RuntimeException('Failed to bind to TCP socket');
        }

        // Listen for connections
        if (!socket_listen($this->socket, 128)) {
            throw new \RuntimeException('Failed to listen on TCP socket');
        }

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
    }
}