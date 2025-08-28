<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TcpService
{
    protected $host;
    protected $port;
    protected $socket;

    public function __construct($host = '127.0.0.1', $port = 9090)
    {
        $this->host = $host;
        $this->port = $port;
    }

    protected function createSocket()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$this->socket) {
            Log::error('❌ فشل في إنشاء سوكيت TCP: ' . socket_strerror(socket_last_error()));
            throw new \Exception('فشل في إنشاء سوكيت TCP');
        }
    }

    public function sendFile(string $filePath, string $filename): bool
    {
        $this->createSocket();

        $connected = socket_connect($this->socket, $this->host, $this->port);

        if (!$connected) {
            Log::error('❌ فشل الاتصال بسيرفر TCP: ' . socket_strerror(socket_last_error($this->socket)));
            socket_close($this->socket);
            return false;
        }

        // إرسال بيانات الميتاداتا (اسم الملف مثلا)
        $metadataMsg = "[METADATA] " . $filename;
        socket_send($this->socket, $metadataMsg, strlen($metadataMsg), 0);

        // إرسال محتوى الملف chunk-by-chunk
        $file = fopen($filePath, 'rb');
        if (!$file) {
            Log::error('❌ فشل في فتح الملف للإرسال: ' . $filePath);
            socket_close($this->socket);
            return false;
        }

        while (!feof($file)) {
            $chunk = fread($file, 4096);
            socket_send($this->socket, $chunk, strlen($chunk), 0);
        }

        fclose($file);

        // إرسال نهاية الملف
        $eofMsg = "[EOF]";
        socket_send($this->socket, $eofMsg, strlen($eofMsg), 0);

        socket_close($this->socket);

        return true;
    }
}
