<?php

namespace Nexus\Socket;

class Config
{
    private static ?Config $instance = null;
    private array $config = [];

    private function __construct()
    {
        // Varsayılan ayarları yükle
        $this->config = [
            'log_path' => '/var/log/microservices/',
            'socket_path' => '/tmp/service/',
            'socket_permissions' => 0770,
            'service_timeout' => 30,
            'max_connections' => 50,
            'debug' => true,
        ];

        // Eğer bir yapılandırma dosyası varsa, onu yükle
        if (file_exists(__DIR__ . '/config/config.php')) {
            $userConfig = include(__DIR__ . '/config/config.php');
            $this->config = array_merge($this->config, $userConfig);
        }

        // Log dizini kontrolü
        if (!file_exists($this->config['log_path'])) {
            mkdir($this->config['log_path'], 0755, true);
        }

        // Socket dizini kontrolü
        if (!file_exists($this->config['socket_path'])) {
            mkdir($this->config['socket_path'], 0755, true);
        }
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}