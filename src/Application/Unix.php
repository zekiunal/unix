<?php

namespace Nexus\Application;

use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nexus\Application\Exception\ServiceException;
use Psr\Log\LoggerInterface;

class Unix
{
    public array $routers = [];
    /**
     * @var \DI\Container
     */
    private Container $container;
    private LoggerInterface $logger;
    protected array $services = [];
    protected bool $running = false;
    protected array $serviceInfo = [];
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->logger = new Logger('application');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
        $this->setupSignalHandlers();
    }

    public function run(): void
    {
        $this->logger ->info("Orchestrator running...");

        $this->logger->info("Orchestrator starting, " . count($this->services) . " service managing");
        $this->running = true;

        foreach ($this->serviceInfo as $name => &$info) {
            $info['status'] = 'running';
        }

        // Sinyalleri işlemek için periyodik olarak pcntl_signal_dispatch() çağır
        while ($this->running) {
            pcntl_signal_dispatch();

            // Bitmiş süreçleri kontrol et ve otomatik olarak yeniden başlat
            foreach ($this->services as $name => $pid) {
                $status = 0;
                $result = pcntl_waitpid($pid, $status, WNOHANG);

                if ($result === $pid) {
                    $exitCode = pcntl_wexitstatus($status);
                    $this->logger->info("Servis sonlandı: $name (PID: $pid, Çıkış kodu: $exitCode)");

                    // Servisin yeniden başlatılması
                    $this->logger->info("Service restarting: $name");
                    $serviceClass = $this->serviceInfo[$name]['class'];
                    $this->registerSocket($serviceClass, $name);
                }
            }

            // CPU kullanımını azaltmak için bekleme
            #usleep(100000); // 100ms
            usleep(1000); // 100ms
        }
    }

    public function setRouters(array $routers): void
    {
        $this->routers = $routers;
    }

    /**
     * @throws \Nexus\Application\Exception\ServiceException
     */
    public function registerSocket(string $serviceClass, string $serviceName): void
    {
        if (!class_exists($serviceClass)) {
            $this->logger->error("Service class not found: $serviceClass");
            throw new ServiceException("Service class not found: $serviceClass");
        }

        $this->logger->info("Service registered: $serviceName ($serviceClass)");

        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->logger->error("Fork unsuccessful!");
            throw new ServiceException("Fork unsuccessful!");
        } else if ($pid) {
            $this->services[$serviceName] = $pid;
            $this->serviceInfo[$serviceName] = [
                'class' => $serviceClass,
                'pid' => $pid,
                'startTime' => time(),
                'status' => 'starting'
            ];
            $this->logger->info("Service started: $serviceName (PID: $pid)");
        } else {
            try {
                cli_set_process_title("php-ms: $serviceName [" . $serviceClass . "]");
                $service = new $serviceClass($serviceName, $this->container);
                $service->listen($this->routers);
            } catch (\Throwable $e) {
                $logger = $this->logger;
                $logger->error("Error: " . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                exit(1);
            }
            exit(0);
        }

        $this->logger->debug("==== Microservice Status ====");
        $this->logger->debug("Ana Orchestrator PID: " . getmypid());
        $this->logger->debug("Running Services:");
        foreach ($this->getStatus() as $service => $info) {
            $this->logger->debug(" - $service (PID: {$info['pid']})");
        }
        $this->logger->debug("===========================");
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
                $this->logger->info("Shutdown signal received, all services are shutting down...");
                $this->shutdown();
                exit(0);
            case SIGHUP:
                $this->logger->info("Reload signal received");
                $this->reload();
                break;
        }
    }

    public function reload(): void
    {
        $this->logger->info("All services restarting...");

        foreach ($this->services as $name => $pid) {
            posix_kill($pid, SIGHUP);
        }
    }

    public function shutdown(): void
    {
        $this->logger->info("Services are shutting down...");
        $this->running = false;

        foreach ($this->services as $name => $pid) {
            $this->logger->info("SIGTERM is being sent to the service $name (PID: $pid)");
            posix_kill($pid, SIGTERM);

            // Wait a little bit for the service to shut down gracefully
            $waitStart = time();
            $terminated = false;

            while (time() - $waitStart < 5) { // 5-second timeout
                $result = pcntl_waitpid($pid, $status, WNOHANG);
                if ($result === $pid) {
                    $this->logger->info("$name Service terminated successfully");
                    $terminated = true;
                    break;
                }
                #usleep(100000); // 100ms
                usleep(1000); // 100ms

            }

            // If it did not shut down gracefully, force termination
            if (!$terminated) {
                $this->logger->info("$name Service is not responding, SIGKILL is being sent");
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }

            unset($this->services[$name]);
            $this->serviceInfo[$name]['status'] = 'stopped';
        }

        $this->logger->info("All services closed");
    }

    public function getStatus(): array
    {
        $status = [];

        foreach ($this->serviceInfo as $name => $info) {
            $pid = $info['pid'];
            $running = posix_kill($pid, 0); // Check if the process is active

            $status[$name] = [
                'pid' => $pid,
                'uptime' => time() - $info['startTime'],
                'status' => $running ? $info['status'] : 'crashed',
                'class' => $info['class']
            ];
        }

        return $status;
    }
}