<?php

namespace Nexus\Socket\Request;

use Nexus\Domain\Repository\RouterRepositoryInterface;
use Nexus\Domain\UseCase\Router\HandleMessageUseCase;
use Nexus\Socket\AuthenticationException;
use Nexus\Socket\Config;
use Nexus\Socket\SocketException;
use Nexus\Socket\TimeoutException;
use Psr\Container\ContainerInterface;

class SocketRequest extends AbstractRequest
{
    protected \Socket|false $socket = false;
    protected string $socketPath;

    /**
     * @throws \Nexus\Socket\SocketException
     */
    public function __construct(string $serviceName, ContainerInterface $container)
    {
        parent::__construct($serviceName, $container);
        $config = Config::getInstance();
        $socketDir = $config->get('socket_path', '/tmp/services/');
        $this->socketPath = $socketDir . "service_$serviceName.sock";
        cli_set_process_title("php-service: $serviceName");
        $this->setupSignalHandlers();
        $this->createSocket();
    }

    private function setupSignalHandlers(): void
    {
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);
    }

    public function handleSignal(int $signal): void
    {
        $this->logger->info("Signal received: $signal");
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->logger->info("Shutdown signal received, service is shutting down...");
                $this->running = false;
                break;
            case SIGHUP:
                $this->logger->info("Reload signal received");
                // Reload special logic here
                break;
        }
    }

    /**
     * @throws \Nexus\Socket\SocketException
     */
    protected function createSocket(): void
    {
        $config = Config::getInstance();

        if (file_exists($this->socketPath)) {
            $this->logger->info("Remove old socket file: $this->socketPath");
            unlink($this->socketPath);
        }

        $this->logger->info("Create new socket: $this->socketPath");

        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->socket === false) {
            $error = socket_strerror(socket_last_error());
            $this->logger->error("Socket could not created: $error");
            throw new SocketException("Socket could not created: $error");
        }

        // Set socket timeout
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);

        if (socket_bind($this->socket, $this->socketPath) === false) {
            $error = socket_strerror(socket_last_error());
            $this->logger->error("Socket could not connect: $error");
            throw new SocketException("Socket could not connect: $error");
        }

        if (socket_listen($this->socket, $config->get('max_connections', 50)) === false) {
            $error = socket_strerror(socket_last_error());
            $this->logger->error("Socket listen could not start: $error");
            throw new SocketException("Socket listen could not start: $error");
        }

        // Set socket permissions (critical for security)
        chmod($this->socketPath, $config->get('socket_permissions', 0770));

        $this->logger->info("Socket started successfully and listening...");
    }

    public function listen($routers): void
    {
        $this->logger->info("[$this->serviceName] Unix socket is listening: $this->socketPath");
        $this->running = true;

        while ($this->running) {
            // Handle signals
            pcntl_signal_dispatch();

            // Accept connection in non-blocking mode
            $client = socket_accept($this->socket);
            if ($client === false) {
                $error = socket_last_error($this->socket);
                // EAGAIN or EWOULDBLOCK errors indicate a timeout, ignore them
                if ($error !== 11 && $error !== 35) {
                    $errorMsg = socket_strerror($error);
                    //$this->logger->error("$this->serviceName - Error while accepting connection: $errorMsg");
                }
                #usleep(100000); // 100ms
                #usleep(10000); // 10ms
                usleep(1000);

                continue;
            }

            $startTime = microtime(true);

            try {
                socket_getpeername($client, $clientAddr);
                #$this->logger->debug("New connection accepted!");

                $message = $this->receiveMessage($client);
                $this->metrics['requests']++;

                #if (!$this->security->authenticateMessage($message)) {
                #$this->logger->error("Authentication error");
                #throw new AuthenticationException("Authentication error");
                #}

                $response = $this->handleMessage($routers, $message);
                $this->sendMessage($client, $response);

                // Update metrics
                $responseTime = microtime(true) - $startTime;
                $this->metrics['totalResponseTime'] += $responseTime;
                $this->metrics['avgResponseTime'] = $this->metrics['totalResponseTime'] / $this->metrics['requests'];

                $this->logger->debug("Request processed", [
                    'path' => $message['path'] ?? '/',
                    #'method' => $message['method'] ?? 'GET',
                    'response' => ($response),
                    'responseTime' => round($responseTime * 1000, 2) . 'ms',
                ]);

            } catch (AuthenticationException $e) {
                $this->metrics['errors']++;
                $this->sendMessage($client, [
                    'status' => 'error',
                    'code' => 401,
                    'message' => 'Unauthorized'
                ]);
            } catch (\Throwable $e) {
                $this->metrics['errors']++;
                $this->logger->error("Error while processing request: " . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);

                $this->sendMessage($client, [
                    'status' => 'error',
                    'code' => 500,
                    'message' => 'Internal Server Error'
                ]);
            } finally {
                socket_close($client);
            }
        }

        $this->logger->info("Service stopped listening");
    }

    /**
     * @throws \Nexus\Socket\SocketException
     * @throws \Nexus\Socket\TimeoutException
     */
    protected function receiveMessage(\Socket $client): array
    {
        $config = Config::getInstance();
        $timeout = $config->get('service_timeout', 30);
        $startTime = time();
        $message = '';

        socket_set_nonblock($client);

        while (true) {
            $buffer = @socket_read($client, 4096);

            if ($buffer === false) {
                $error = socket_last_error($client);
                // EAGAIN or EWOULDBLOCK errors for waiting for more data
                if ($error === 11 || $error === 35) {
                    // Timeout check
                    if (time() - $startTime > $timeout) {
                        $this->logger->error("Timeout while receiving message");
                        throw new TimeoutException("Timeout while receiving message");
                    }
                    #usleep(10000); // 10ms
                    usleep(1000);

                    continue;
                } else {
                    $errorMsg = socket_strerror($error);
                    $this->logger->error("Error while reading message: $errorMsg");
                    throw new SocketException("Error while reading message: $errorMsg");
                }
            }

            if ($buffer === '' || $buffer === null) {
                break; // Connection closed or all data read
            }

            $message .= $buffer;

            // Check if the entire message has been received
            if (strlen($buffer) < 4096) {
                break;
            }
        }

        socket_set_block($client);

        if (empty($message)) {
            $this->logger->error("Empty message received");
            throw new SocketException("Empty message received");
        }

        $decoded = json_decode($message, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Invalid JSON: " . json_last_error_msg());
            throw new SocketException("Invalid JSON: " . json_last_error_msg());
        }

        return $decoded;
    }

    protected function handleMessage(array $routes, array $message)
    {
        $handleMessageUseCase = new HandleMessageUseCase($this->container->get(RouterRepositoryInterface::class));
        $response = $handleMessageUseCase->execute($message);
        unset($handleMessageUseCase);
        return $response;
    }

    /**
     * @throws \Nexus\Socket\SocketException
     */
    protected function sendMessage(\Socket $client, array $data): void
    {
        $message = json_encode($data);
        if ($message === false) {
            $this->logger->error("JSON encoding failed: " . json_last_error_msg());
            throw new SocketException("JSON encoding failed: " . json_last_error_msg());
        }

        $messageLength = strlen($message);
        $sent = 0;

        while ($sent < $messageLength) {
            $result = @socket_write($client, substr($message, $sent), $messageLength - $sent);

            if ($result === false) {
                $error = socket_strerror(socket_last_error($client));
                $this->logger->error("Error while sending message: $error");
                throw new SocketException("Error while sending message: $error");
            }

            $sent += $result;
        }
    }
}