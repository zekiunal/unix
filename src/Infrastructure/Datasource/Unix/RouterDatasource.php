<?php

namespace Nexus\Infrastructure\Datasource\Unix;

use Nexus\Domain\Datasource\RouterDatasourceInterface;

class RouterDatasource implements RouterDatasourceInterface
{
    private string $socketPath;

    public function __construct()
    {
        $this->socketPath = "/tmp/service/service_router.sock";
    }

    public function handleMessage(array $message): array
    {
        $method = $message['method'] ?? 'GET';
        $path = $message['path'] ?? '/';
        $data = $message['data'] ?? [];

        $client = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (@socket_connect($client, $this->socketPath)) {
            $request = json_encode([
                'path'       => '/',
                'method'     => 'GET',
                'auth_token' => '',
                'data'       => [
                    'method'  => $method,
                    'uri'  => $path,
                    'data' => $data

                ]
            ]);
            socket_write($client, $request, strlen($request));

            $response = '';
            while ($buffer = socket_read($client, 4096)) {
                $response .= $buffer;
                if (strlen($buffer) < 4096) break;
            }
            echo "Response:" . $response . "\n";
            $res = json_decode($response, true);

            $controllerClass = $res['handler']['controller'];
            $action =  $res['handler']['action'];
            $controller = new $controllerClass();
            $controller->setData($data);

            if (method_exists($controller, 'setTemplate') && isset( $res['handler']['template'])) {
                $controller->setTemplate($res['handler']['template']);
            }

            return call_user_func_array([$controller, $action], $res['vars']);
        }
        return [];
    }
}