<?php

namespace Nexus\Socket;
class Security
{
    private static $instance = null;
    private $authToken;

    private function __construct()
    {
        // Güvenlik token'ını dosyadan yükle veya oluştur
        $tokenFile = BASE_DIR . '/../.auth_token';
        if (file_exists($tokenFile)) {
            $this->authToken = trim(file_get_contents($tokenFile));
        } else {
            $this->authToken = $this->generateToken();
            file_put_contents($tokenFile, $this->authToken);
            chmod($tokenFile, 0600); // Sadece sahibi okuyabilir
        }
    }

    public static function getInstance(): Security
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function getToken(): string
    {
        return $this->authToken;
    }

    public function validateToken(string $token): bool
    {
        return hash_equals($this->authToken, $token);
    }

    public function authenticateMessage(array $message): bool
    {
        return isset($message['auth_token']) && $this->validateToken($message['auth_token']);
    }
}