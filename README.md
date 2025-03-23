# Ody/Process Documentation

## Introduction

Ody/Process is a PHP package designed to manage background processes within the ODY framework. This package allows you
to
create and manage background processes that run alongside your main application, leveraging Swoole's powerful coroutine
capabilities for high-performance concurrent processing.

## Installation

Install the package via Composer:

```bash
composer require ody/process
```

## Configuration

Create a `process.php` configuration file in your `config` directory:

```php
<?php

return [
    'max_processes' => 256,
    'enable_coroutine' => true,
    "processes" => [
        // Register your standard processes here
        \App\Processes\MyProcess::class
    ],
    "tcp_processes" => [
        // Register TCP processes with configuration
        \App\Processes\MyTcpProcess::class => [
            'host' => '127.0.0.1',
            'port' => 9501
        ]
    ],
    "unix_processes" => [
        // Register Unix socket processes with configuration
        \App\Processes\MyUnixProcess::class => [
            'socket_path' => '/tmp/my_socket.sock'
        ]
    ]
];
```

## Types of Processes

Ody/Process supports three types of processes:

1. **Standard Processes**: Basic processes that run in the background
2. **TCP Processes**: Processes that listen on a TCP socket for communication
3. **Unix Socket Processes**: Processes that use Unix domain sockets for communication

## Creating Process Classes

### Standard Process

Standard processes are ideal for "set and forget" background tasks that don't require external communication.

```php
<?php

namespace App\Processes;

use Ody\Process\StandardProcess;
use Swoole\Timer;

class MyProcess extends StandardProcess
{
    public function handle(): void
    {
        // Initial setup
        echo "Process started\n";
        
        // Run continuously
        while ($this->running) {
            // Process signals for clean shutdown
            pcntl_signal_dispatch();
            
            // Your process logic here
            
            // Avoid CPU hogging
            sleep(1);
        }
        
        echo "Process stopped\n";
    }
    
    protected function processMessage(string $data): ?string
    {
        // This is called when the process receives a message
        // For standard processes, this is rarely used directly
        return "Received: $data";
    }
}
```

### TCP Process

TCP processes allow communication with the process via a TCP socket.

```php
<?php

namespace App\Processes;

use Ody\Process\TcpProcess;

class MyTcpProcess extends TcpProcess
{
    protected function processMessage(string $data): ?string
    {
        // Process incoming message from TCP socket
        $command = json_decode($data, true);
        
        if (!$command || !isset($command['action'])) {
            return json_encode(['error' => 'Invalid command format']);
        }
        
        switch ($command['action']) {
            case 'ping':
                return json_encode(['status' => 'ok', 'message' => 'pong']);
                
            case 'getData':
                $result = $this->fetchSomeData();
                return json_encode(['status' => 'ok', 'data' => $result]);
                
            default:
                return json_encode(['error' => 'Unknown action']);
        }
    }
    
    private function fetchSomeData()
    {
        // Your implementation
        return ['sample' => 'data'];
    }
}
```

### Unix Socket Process

Unix socket processes are similar to TCP processes but use Unix domain sockets which are faster for local
communications.

```php
<?php

namespace App\Processes;

use Ody\Process\UnixProcess;

class MyUnixProcess extends UnixProcess
{
    protected function processMessage(string $data): ?string
    {
        // Similar implementation to TCP process
        $command = json_decode($data, true);
        
        // Process the command
        // ...
        
        return json_encode(['status' => 'completed']);
    }
}
```

## Service Provider Integration

Register the `ProcessServiceProvider` in your application to automatically start processes when your server boots:

```php
// In config/app.php
\Ody\Process\Providers\ProcessServiceProvider::class;
```

## Communicating with Processes

### With TCP Processes

Create a client to communicate with the process:

```php
<?php

// Connect to the TCP socket
$client = stream_socket_client("tcp://127.0.0.1:9501", $errno, $errstr);

if (!$client) {
    echo "Error: Cannot connect to process: $errstr ($errno)\n";
    exit(1);
}

// Send a command
$command = json_encode(['action' => 'getData']);
fwrite($client, $command);

// Read the response
$response = '';
while (!feof($client)) {
    $response .= fread($client, 8192);
}
fclose($client);

// Process the response
$result = json_decode($response, true);
print_r($result);
```

### With Unix Socket Processes

Similar to TCP but using a Unix socket path:

```php
<?php

// Connect to the Unix socket
$client = stream_socket_client("unix:///tmp/my_socket.sock", $errno, $errstr);

if (!$client) {
    echo "Error: Cannot connect to process: $errstr ($errno)\n";
    exit(1);
}

// Send a command
$command = json_encode(['action' => 'getData']);
fwrite($client, $command);

// Read the response
$response = fread($client, 8192);
fclose($client);

// Process the response
$result = json_decode($response, true);
print_r($result);
```

## Process Management

### Manual Process Management

You can also programmatically manage processes using the Process facade. This only works before the actual server
starts,
executing a process on a running server cannot be done.

```php
<?php

use Ody\Process\Process;
use Ody\Process\ProcessManager;

// Execute a standard process
$pid = Process::execute(\App\Processes\MyProcess::class);

// Execute a TCP process
$tcpProcess = ProcessManager::executeTcp(\App\Processes\MyTcpProcess::class, [
    'host' => '127.0.0.1',
    'port' => 9502
]);

// Execute a Unix socket process
$unixProcess = ProcessManager::executeUnix(\App\Processes\MyUnixProcess::class, [
    'socket_path' => '/tmp/custom_socket.sock'
]);

// Check if a process is running
$isRunning = Process::isRunning(\App\Processes\MyProcess::class);

// Kill a process
Process::kill($pid);
```

## Best Practices

1. **Proper Signal Handling**

   Always implement proper signal handling to ensure graceful shutdown:

   ```php
   while ($this->running) {
       pcntl_signal_dispatch();
       // Your logic
       sleep(1);
   }
   ```

2. **Resource Cleanup**

   Make sure to clean up resources when your process stops:

   ```php
   public function handle(): void
   {
       $resource = openSomeResource();
       
       while ($this->running) {
           // Process work
       }
       
       // Clean up
       closeSomeResource($resource);
   }
   ```

3. **Avoiding Memory Leaks**

   For long-running processes, be careful about memory usage:

   ```php
   // Bad practice - accumulating data
   $allData = [];
   while ($this->running) {
       $allData[] = fetchSomeData();
   }
   
   // Better practice - process and discard
   while ($this->running) {
       $data = fetchSomeData();
       processSomeData($data);
       unset($data); // Explicitly remove reference
   }
   ```

4. **Appropriate Process Type Selection**

    - Use **StandardProcess** for background tasks that don't need external communication
    - Use **TcpProcess** for services that need to be accessible over the network
    - Use **UnixProcess** for high-performance local inter-process communication

5. **Communication Protocol**

   Use a consistent protocol (like JSON) for communication with TCP and Unix socket processes:

   ```php
   // Request format
   $request = json_encode([
       'action' => 'command',
       'parameters' => ['key' => 'value']
   ]);
   
   // Response format
   $response = json_encode([
       'status' => 'success|error',
       'data' => $result,
       'message' => 'Human readable message'
   ]);
   ```

## Troubleshooting

### Process Not Starting

- Check your server logs for PHP errors
- Verify that the process class implements the correct interface
- Ensure the class path in your configuration is correct

### Communication Timeouts

- Make sure the process is running (check with `ps aux`)
- Verify the port or socket path is correct
- Check for firewall rules that might be blocking communication

### Process Memory Usage

Monitor process memory usage with:

```bash
ps -o pid,user,%mem,command ax | grep YourFramework
```

### Killing Stuck Processes

If a process is stuck and unresponsive:

```bash
# Find the process
ps aux | grep ODY

# Force kill
kill -9 <PID>
```

## Advanced Usage

### Using Coroutines

When `enable_coroutine` is set to `true`, you can use Swoole coroutines in your processes:

```php
use Swoole\Coroutine;

public function handle(): void
{
    while ($this->running) {
        pcntl_signal_dispatch();
        
        // Create multiple concurrent tasks
        for ($i = 0; $i < 10; $i++) {
            Coroutine::create(function() use ($i) {
                // Each task runs in its own coroutine
                $this->processTask($i);
            });
        }
        
        Coroutine::sleep(1); // Non-blocking sleep
    }
}
```

### Sharing State Between Coroutines

Use Swoole's Table for sharing state between coroutines:

```php
use Swoole\Table;

private Table $statsTable;

public function handle(): void
{
    // Create shared memory table
    $this->statsTable = new Table(1024);
    $this->statsTable->column('counter', Table::TYPE_INT);
    $this->statsTable->column('last_update', Table::TYPE_INT);
    $this->statsTable->create();
    
    while ($this->running) {
        // Your process logic
        Coroutine::create(function() {
            $this->statsTable->incr('stats', 'counter', 1);
            $this->statsTable->set('stats', ['last_update' => time()]);
        });
    }
}
```

## Example Use Cases

1. **Task Queue Processor**: Process background tasks from a queue
2. **File Watcher**: Monitor file changes and trigger actions
3. **Data Aggregator**: Collect and process metrics in the background
4. **WebSocket Server**: Implement real-time communication
5. **Scheduled Jobs Runner**: Execute tasks at specific intervals
6. **API Proxy**: Create a middleware for external API communications
