<?php
namespace Ody\Process\Examples;

require_once __DIR__ . "/../../vendor/autoload.php";
/**
 * Example usage
 */

use Ody\Process\Process;
use Ody\Process\ProcessManager;

// Initialize the process manager
ProcessManager::init();

// Example 1: Standard process
$pid = Process::execute(EchoProcess::class);

// Send data to the process
$process = new \Swoole\Process(function ($worker) use ($pid) {
    // Send data
    $worker->write("Hello, process $pid!");

    // Wait for response
    $response = $worker->read();
    echo "Received: $response\n";
}, true);
$process->start();

// Example 2: Unix socket process
$unixProcess = ProcessManager::executeUnix(LoggerProcess::class, [
    'log_file' => '/tmp/my_app.log'
]);

// Connect to the Unix socket
$client = stream_socket_client("unix://{$unixProcess['socket_path']}", $errno, $errstr);
if ($client) {
    fwrite($client, "This is a log message via Unix socket");
    $response = fread($client, 8192);
    echo "Response: $response\n";
    fclose($client);
}

// Example 3: TCP process
$tcpProcess = ProcessManager::executeTcp(HttpProxyProcess::class, [
    'host' => '127.0.0.1',
    'port' => 0 // Auto-assign port
]);

// Connect to the TCP server
$client = stream_socket_client("tcp://127.0.0.1:{$tcpProcess['port']}", $errno, $errstr);
if ($client) {
    // Send a simple HTTP request
    fwrite($client, "GET /test HTTP/1.1\r\nHost: localhost\r\n\r\n");
    $response = '';
    while (!feof($client)) {
        $response .= fread($client, 8192);
    }
    echo "HTTP Response: $response\n";
    fclose($client);
}

// Clean up
ProcessManager::kill($pid);
ProcessManager::kill($unixProcess['pid']);
ProcessManager::kill($tcpProcess['pid']);
