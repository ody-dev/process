<?php

/**
 * Example of a TCP process
 */
namespace Ody\Process\Examples;

use Ody\Process\TcpProcess;

class HttpProxyProcess extends TcpProcess
{
    protected function processMessage(string $data): ?string
    {
        // Parse incoming HTTP request
        $request = $this->parseHttpRequest($data);

        // Forward to target
        $response = $this->forwardRequest($request);

        // Return HTTP response
        return $response;
    }

    private function parseHttpRequest(string $data): array
    {
        // Simple HTTP request parsing
        $lines = explode("\r\n", $data);
        $firstLine = explode(' ', $lines[0]);

        $headers = [];
        $currentIndex = 1;

        while (isset($lines[$currentIndex]) && $lines[$currentIndex] !== '') {
            $headerParts = explode(':', $lines[$currentIndex], 2);
            if (count($headerParts) === 2) {
                $headers[trim($headerParts[0])] = trim($headerParts[1]);
            }
            $currentIndex++;
        }

        $body = '';
        if (isset($lines[$currentIndex + 1])) {
            $body = implode("\r\n", array_slice($lines, $currentIndex + 1));
        }

        return [
            'method' => $firstLine[0] ?? 'GET',
            'path' => $firstLine[1] ?? '/',
            'version' => $firstLine[2] ?? 'HTTP/1.1',
            'headers' => $headers,
            'body' => $body
        ];
    }

    private function forwardRequest(array $request): string
    {
        // In a real implementation, you would forward the request to a target server
        // For this example, we'll just create a simple response

        $body = json_encode([
            'success' => true,
            'message' => 'Request proxied successfully',
            'request' => [
                'method' => $request['method'],
                'path' => $request['path']
            ]
        ]);

        return "HTTP/1.1 200 OK\r\n" .
            "Content-Type: application/json\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "\r\n" .
            $body;
    }
}