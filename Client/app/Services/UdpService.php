<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class UdpService
{
    protected $host;
    protected $port;
    protected $socket;

    public function __construct($host = '192.168.137.1', $port = 9092)
    {
        $this->host = $host;
        $this->port = $port;
        $this->createSocket();
    }

    protected function createSocket()
    {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if (!$this->socket) {
            Log::error('❌ فشل في إنشاء سوكيت UDP: ' . socket_strerror(socket_last_error()));
            throw new \Exception('فشل في إنشاء سوكيت UDP');
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_BROADCAST, 1);
    }

    public function send(string $message): bool
    {
      return $this->sendMessage($message);
    }
    public function sendMessage(string $message): bool
{
    $encoded = mb_convert_encoding($message, 'UTF-8');
    $sent = socket_sendto($this->socket, $encoded, strlen($encoded), 0, $this->host, $this->port);

    if ($sent === false) {
    Log::error('❌ فشل في إرسال الرسالة عبر UDP: ' . socket_strerror(socket_last_error($this->socket)));
    return false;
    } 
    else 
    {
    Log::info("✅ تم إرسال الرسالة إلى UDP: " . $message);
    }


    // logging success
    return true;
}

    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }
}
