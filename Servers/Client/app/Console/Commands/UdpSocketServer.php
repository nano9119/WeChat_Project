<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UdpSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:udp-socket-server';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a UDP Socket Server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = '127.0.0.1';
        $port = 8080;

        // إنشاء سوكيت UDP
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            $this->error("Failed to create socket");
            return;
        }

        // ربط السوكيت بالمضيف والبورت
        if (!socket_bind($socket, $host, $port)) {
            $this->error("Failed to bind socket");
            return;
        }

        $this->info("UDP Socket Server is running on $host:$port");

        while (true) {
            $buf = '';
            $remote_ip = '';
            $remote_port = 0;

            // استلام البيانات من العميل
            socket_recvfrom($socket, $buf, 512, 0, $remote_ip, $remote_port);

            $this->info("Received from $remote_ip:$remote_port: $buf");

            // إرسال رد
            $response = "Laravel UDP Server received: [$buf]";
            socket_sendto($socket, $response, strlen($response), 0, $remote_ip, $remote_port);
        }

        socket_close($socket);
    }
}
