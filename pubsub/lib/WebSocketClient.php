<?php

namespace Lib;

/** 
 *  This class is mostly generated with AI, and should be refactored for a proper usage.
 * 
 */

/**
 * A pure PHP WebSocket client class.
 * This class handles the WebSocket handshake, message framing,
 * and maintains a persistent connection with a server.
 * It is designed to be used in a command-line environment or a
 * long-running script.
 */
class WebSocketClient
{
    private string $url;
    private ?string $host;
    private ?int $port;
    private ?string $path;
    private ?bool $secure;
    private $socket;
    private bool $connected = false;
    private array $subscribedChannels = [];

    /**
     * WebSocketClient constructor.
     *
     * @param string $url The WebSocket server URL (e.g., 'ws://localhost:8080/chat').
     */
    public function __construct(string $url)
    {
        $this->url = $url;
        $this->parseUrl();
    }

    /**
     * Connects to the WebSocket server and starts listening for messages.
     * This method will continuously attempt to reconnect on failure.
     */
    public function connectAndListen(): void
    {
        while (true) {
            try {
                if (!$this->connected) {
                    $this->connect();
                }

                // Main message listening loop
                while ($this->connected) {
                    $read = [$this->socket];
                    $write = $except = null;

                    // Wait for socket activity with a timeout of 1 second
                    $num_changed_sockets = stream_select($read, $write, $except, 1);

                    if ($num_changed_sockets === false) {
                        echo "Stream select error. Reconnecting...\n";
                        $this->disconnect();
                        break;
                    } elseif ($num_changed_sockets > 0) {
                        $payload = fread($this->socket, 2048);
                        if ($payload === false || $payload === '') {
                            echo "Connection closed by server. Reconnecting...\n";
                            $this->disconnect();
                            break;
                        }

                        $decoded = $this->decode($payload);

                        // Assuming decoded payload is a JSON object with 'channel' and 'data'
                        $decodedObject = json_decode($decoded);
                        $decodedObject = $decodedObject->data;

                        if (
                            json_last_error() === JSON_ERROR_NONE && isset($decodedObject->channel)
                            && isset($decodedObject->data)
                            && in_array($decodedObject->channel, $this->subscribedChannels)
                        ) {
                             echo "Received message from channel '{$decodedObject->channel}': {$decodedObject->data}\n";
                        }
                    }
                }
            } catch (\Exception $e) {
                echo "An error occurred: " . $e->getMessage() . "\n";
                $this->disconnect();
            }

            echo "Reconnecting in 2 seconds...\n";
            sleep(2);
        }
    }

    /**
     * Subscribes to a specific channel.
     *
     * @param string $channel The name of the channel to subscribe to.
     * @return bool True on success, false on failure.
     */
    public function subscribeToChannel(string $channel): bool
    {
        if (!in_array($channel, $this->subscribedChannels)) {
            $this->subscribedChannels[] = $channel;
        }

        return $this->sendAction('subscribe', ['channel' => $channel]);
    }

    /**
     * Unsubscribes from a specific channel.
     *
     * @param string $channel The name of the channel to unsubscribe from.
     * @return bool True on success, false on failure.
     */
    public function unsubscribeFromChannel(string $channel): bool
    {
        $key = array_search($channel, $this->subscribedChannels);
        if ($key !== false) {
            unset($this->subscribedChannels[$key]);
        }

        return $this->sendAction('unsubscribe', ['channel' => $channel]);
    }

    /**
     * Sends a message to a specific channel.
     *
     * @param string $channel The name of the channel to send the message to.
     * @param string $message The message content.
     * @return bool True on success, false on failure.
     */
    public function sendToChannel(string $channel, string $message): bool
    {
        $data = [
            'channel' => $channel,
            'data' => $message
        ];
        return $this->sendAction('message', $data);
    }

    /**
     * Closes the WebSocket connection.
     */
    public function close(): void
    {
        if ($this->connected) {
            $this->sendAction('close', []);
            $this->disconnect();
        }
    }

    /**
     * Parses the WebSocket URL to extract host, port, and path.
     */
    private function parseUrl(): void
    {
        $urlParts = parse_url($this->url);
        if ($urlParts === false || !isset($urlParts['host'])) {
            throw new \InvalidArgumentException("Invalid WebSocket URL provided.");
        }
        $this->host = $urlParts['host'];
        $this->port = $urlParts['port'] ?? ($urlParts['scheme'] === 'wss' ? 443 : 80);
        $this->path = $urlParts['path'] ?? '/';
        $this->secure = ($urlParts['scheme'] === 'wss');
    }

    /**
     * Establishes a connection and performs the WebSocket handshake.
     *
     * @throws \Exception If the connection or handshake fails.
     */
    private function connect(): void
    {
        echo "Attempting to connect to " . $this->url . "\n";

        $socket_protocol = $this->secure ? 'ssl://' : 'tcp://';
        $this->socket = @stream_socket_client(
            $socket_protocol . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->socket) {
            throw new \Exception("Could not connect to WebSocket server: ($errno) $errstr");
        }

        // Handshake
        $key = $this->generateKey();
        $headers = "GET {$this->path} HTTP/1.1\r\n";
        $headers .= "Host: {$this->host}\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Key: {$key}\r\n";
        $headers .= "Sec-WebSocket-Version: 13\r\n";
        $headers .= "\r\n";

        fwrite($this->socket, $headers);
        $response = fread($this->socket, 2048);

        if (strpos($response, '101 Switching Protocols') === false) {
            $this->disconnect();
            throw new \Exception("WebSocket handshake failed. Response: " . substr($response, 0, 100));
        }

        stream_set_blocking($this->socket, 0);
        $this->connected = true;
        echo "Successfully connected to WebSocket server.\n";

        // Re-subscribe to channels after reconnecting
        foreach ($this->subscribedChannels as $channel) {
            $this->sendAction('subscribe', ['channel' => $channel]);
        }
    }

    /**
     * Disconnects the socket and resets the connected state.
     */
    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            echo "Socket connection closed.\n";
        }
        $this->connected = false;
        $this->socket = null;
    }

    /**
     * Generates a random WebSocket key for the handshake.
     *
     * @return string The base64-encoded key.
     */
    private function generateKey(): string
    {
        $key = openssl_random_pseudo_bytes(16);
        return base64_encode($key);
    }
    
    /**
     * Sends a structured action message to the server.
     *
     * @param string $action The action type (e.g., 'subscribe', 'message').
     * @param array $data The data to send with the action.
     * @return bool True on success, false on failure.
     */
    private function sendAction(string $action, array $data): bool
    {
        if (!$this->connected) {
            $this->connect();
        }

        $payload = json_encode(['action' => $action, 'data' => $data]);
        $frame = $this->encode($payload, 'text');
        return fwrite($this->socket, $frame) !== false;
    }

    /**
     * Encodes a message into a WebSocket frame.
     *
     * @param string $payload The message to encode.
     * @param string $type The message type ('text' or 'binary').
     * @param bool $masked Whether to mask the payload (required for clients).
     * @return string The encoded WebSocket frame.
     */
    private function encode(string $payload, string $type = 'text', bool $masked = true): string
    {
        $frame = '';
        $payloadLength = strlen($payload);

        // Set the first byte (FIN bit and opcode)
        switch ($type) {
            case 'text':
                $frame .= chr(0x81);
                break;
            case 'binary':
                $frame .= chr(0x82);
                break;
            case 'close':
                $frame .= chr(0x88);
                break;
            case 'ping':
                $frame .= chr(0x89);
                break;
            case 'pong':
                $frame .= chr(0x8A);
                break;
            default:
                throw new \InvalidArgumentException("Invalid message type provided.");
        }

        // Set the second byte (Mask bit and Payload length)
        $maskBit = $masked ? 0x80 : 0;
        if ($payloadLength <= 125) {
            $frame .= chr($maskBit | $payloadLength);
        } elseif ($payloadLength > 125 && $payloadLength < 65536) {
            $frame .= chr($maskBit | 0x7E);
            $frame .= chr(($payloadLength >> 8) & 0xFF);
            $frame .= chr($payloadLength & 0xFF);
        } else {
            $frame .= chr($maskBit | 0x7F);
            $frame .= chr(($payloadLength >> 56) & 0xFF);
            $frame .= chr(($payloadLength >> 48) & 0xFF);
            $frame .= chr(($payloadLength >> 40) & 0xFF);
            $frame .= chr(($payloadLength >> 32) & 0xFF);
            $frame .= chr(($payloadLength >> 24) & 0xFF);
            $frame .= chr(($payloadLength >> 16) & 0xFF);
            $frame .= chr(($payloadLength >> 8) & 0xFF);
            $frame .= chr($payloadLength & 0xFF);
        }

        // Mask the payload (required for clients)
        if ($masked) {
            $mask = openssl_random_pseudo_bytes(4);
            $frame .= $mask;
            for ($i = 0; $i < $payloadLength; $i++) {
                $frame .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }
        } else {
            $frame .= $payload;
        }

        return $frame;
    }

    /**
     * Decodes a WebSocket frame.
     * This is a simplified decoder and may not handle all edge cases.
     *
     * @param string $data The raw WebSocket frame data.
     * @return string The decoded payload.
     * @throws \Exception If decoding fails.
     */
    private function decode(string $data): string
    {
        $frame = new \stdClass();
        $frame->fin = (ord($data[0]) >> 7) & 1;
        $frame->opcode = ord($data[0]) & 0x0F;
        $frame->masked = (ord($data[1]) >> 7) & 1;
        $frame->payload_length = ord($data[1]) & 0x7F;
        $offset = 2;

        if ($frame->payload_length === 0x7E) {
            $frame->payload_length = (ord($data[2]) << 8) | ord($data[3]);
            $offset = 4;
        } elseif ($frame->payload_length === 0x7F) {
            // This is a simplified client; we don't handle 64-bit lengths
            throw new \Exception("Large frame sizes not supported.");
        }

        if ($frame->masked) {
            $mask = substr($data, $offset, 4);
            $offset += 4;
        }

        $payload = substr($data, $offset, $frame->payload_length);

        if ($frame->masked) {
            for ($i = 0; $i < $frame->payload_length; $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }
        }

        if ($frame->opcode === 0x08) { // Close frame
            $this->disconnect();
            throw new \Exception("Received close frame from server.");
        }

        return $payload;
    }
}
