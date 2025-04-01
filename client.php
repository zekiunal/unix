<?php

$i = 0;

while (true) {
    $client = socket_create(AF_UNIX, SOCK_STREAM, 0);
    $socketPath = "/tmp/service/service_home.sock";
    try {
        if (@socket_connect($client, $socketPath)) {
            $tokenFile = '/.auth_token';
            if (file_exists($tokenFile)) {
                $authToken = trim(file_get_contents($tokenFile));
            }

            $request = json_encode([
                'path'       => '/',
                'method'     => 'GET',
                'auth_token' => $authToken ?? '',
                'data'       => [
                    'key'  => 'value',
                    'rand' => rand(1, 10000000)
                ]
            ]);


            socket_write($client, $request, strlen($request));

            $response = '';
            while ($buffer = socket_read($client, 4096)) {
                $response .= $buffer;
                if (strlen($buffer) < 4096) break;
            }
            $i++;
            echo "Response $i: " . $response . "\n";

        } else {
            echo "Waiting connection $socketPath (" . socket_strerror(socket_last_error($client)) . ")\n";
        }
    } catch (\Throwable $e) {
        echo $e->getMessage();
    }
    socket_close($client);
    #sleep(1);
}
