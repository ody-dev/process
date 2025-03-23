<?php
/**
 * Command line client for ChatServer process
 *
 * Usage:
 *   php chat-client.php join [username]        # Join the chat with a username
 *   php chat-client.php send [id] [message]    # Send a message using your client ID
 *   php chat-client.php history [limit]        # Get chat history (default 10 messages)
 */

// Configuration
$host = '127.0.0.1';
$port = 9511; // Must match the port in your config/process.php

// Get command from arguments
if ($argc < 2) {
    echo "Error: Command required\n";
    showUsage();
    exit(1);
}

$command = $argv[1];

// Connect to the chat server
$client = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 30);

if (!$client) {
    echo "Error: Cannot connect to chat server at {$host}:{$port}: {$errstr} ({$errno})\n";
    exit(1);
}

// Process different commands
switch ($command) {
    case 'join':
        $username = $argv[2] ?? 'User' . rand(1000, 9999);
        $data = ['action' => 'join', 'username' => $username];

        echo "Joining chat as '{$username}'...\n";
        break;

    case 'send':
        if ($argc < 4) {
            echo "Error: Client ID and message required\n";
            showUsage();
            exit(1);
        }

        $clientId = $argv[2];
        $message = $argv[3];

        $data = ['action' => 'message', 'client_id' => $clientId, 'content' => $message];
        echo "Sending message...\n";
        break;

    case 'history':
        $limit = $argv[2] ?? 10;
        $data = ['action' => 'history', 'limit' => (int)$limit];

        echo "Getting chat history (last {$limit} messages)...\n";
        break;

    default:
        echo "Error: Unknown command '{$command}'\n";
        showUsage();
        exit(1);
}

// Send request
fwrite($client, json_encode($data));

// Read response (with timeout)
stream_set_timeout($client, 5);
$response = '';
while (!feof($client)) {
    $response .= fread($client, 8192);
    $info = stream_get_meta_data($client);
    if ($info['timed_out']) {
        echo "Error: Connection timed out\n";
        break;
    }
}

// Close connection
fclose($client);

// Process and display response
if ($response) {
    $result = json_decode($response, true);

    if (!$result) {
        echo "Error: Invalid response from server\n";
        echo "Raw response: " . substr($response, 0, 100) . "...\n";
        exit(1);
    }

    if (isset($result['error'])) {
        echo "Error: {$result['error']}\n";
        exit(1);
    }

    // Handle response based on command
    switch ($command) {
        case 'join':
            echo "Successfully joined chat!\n";
            echo "Your client ID: {$result['client_id']}\n";
            echo "Message: {$result['message']}\n";

            if (!empty($result['history'])) {
                echo "\nRecent messages:\n";
                displayHistory($result['history']);
            }

            echo "\nKeep this client ID to send messages: {$result['client_id']}\n";
            break;

        case 'send':
            echo "Message sent successfully\n";
            break;

        case 'history':
            if (empty($result['history'])) {
                echo "No messages in chat history\n";
            } else {
                echo "Chat history:\n";
                displayHistory($result['history']);
            }
            break;
    }
} else {
    echo "Error: No response from server\n";
}

/**
 * Display chat history in a readable format
 */
function displayHistory(array $history): void
{
    foreach ($history as $msg) {
        $time = date('H:i:s', $msg['timestamp']);

        if ($msg['type'] === 'system') {
            echo "[{$time}] SYSTEM: {$msg['content']}\n";
        } else {
            echo "[{$time}] {$msg['username']}: {$msg['content']}\n";
        }
    }
}

/**
 * Show usage instructions
 */
function showUsage(): void
{
    echo "Usage:\n";
    echo "  php chat-client.php join [username]        # Join the chat with a username\n";
    echo "  php chat-client.php send [id] [message]    # Send a message using your client ID\n";
    echo "  php chat-client.php history [limit]        # Get chat history (default 10 messages)\n";
}