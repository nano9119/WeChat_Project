<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ListenServerIP extends Command
{
    protected $signature = 'server:listen-ip';
    protected $description = 'Listen for server broadcasts and store IP dynamically';

    protected $port = 9091; // نفس البورت الذي يبث عليه السيرفر

    public function handle()
    {
        $this->info("🟢 Listening for server broadcasts on UDP port {$this->port}...");

        // إنشاء سوكيت UDP
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            Log::error('❌ فشل إنشاء سوكيت UDP: ' . socket_strerror(socket_last_error()));
            $this->error('Failed to create UDP socket.');
            return 1;
        }

        // ربط السوكيت بأي IP على البورت المحدد
        if (!socket_bind($socket, '0.0.0.0', $this->port)) {
            Log::error('❌ فشل bind لسوكيت UDP: ' . socket_strerror(socket_last_error($socket)));
            $this->error('Failed to bind UDP socket.');
            return 1;
        }

        while (true) {
            $buf = '';
            $from = '';
            $port = 0;

            // استقبال الرسائل
            socket_recvfrom($socket, $buf, 1024, 0, $from, $port);

            // تخزين الـ IP في cache لمدة 60 ثانية
            Cache::put('server_ip', $from, 60);

            $this->info("📡 Server discovered: $from | Message: $buf");
            Log::info("Server discovered: $from | Message: $buf");
        }

        socket_close($socket);
    }
}
