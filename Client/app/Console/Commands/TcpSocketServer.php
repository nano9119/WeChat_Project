<?php

namespace App\Console\Commands;
use app\Console\Kernel;
use Illuminate\Console\Command;

class TcpSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tcp-socket-server';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a TCP Socket Server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = '127.0.0.1';
        $port = 8080;

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, $host, $port);
        socket_listen($socket);

        $this->info("TCP Socket Server started on $host:$port");

        while (true) {
            $client = socket_accept($socket); // ينتظر اتصال جديد
            $data = socket_read($client, 1024); // يقرأ البيانات المرسلة من العميل
            $this->info("Received: " . $data);

            $response = "Laravel TCP response: received [$data]";
            socket_write($client, $response, strlen($response));  // يرسل الرد
            socket_close($client);// يغلق الاتصال مع العميل
        }

        socket_close($socket);
    }
}
