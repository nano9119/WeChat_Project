<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class UdpClient extends Command
{
    protected $signature = 'udp:send';
    protected $description = 'إرسال رسائل إلى سيرفر UDP';

    public function handle()
    {
        $host = "255.255.255.255"; // Broadcast IP
        $port = 9092;              // نفس بورت استقبال الرسائل في السيرفر

        // إدخال اسم المستخدم (لأننا ما بنقدر نستخدم auth()->user() داخل الكونسول)
        $userName = $this->ask("أدخل اسم المستخدم");

        // إنشاء سوكيت UDP
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            $this->error("❌ فشل في إنشاء السوكيت: " . socket_strerror(socket_last_error()));
            return;
        }

        // تفعيل الإرسال للبث
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);

        $this->info("✅ جاهز للإرسال. اكتب 'exit' للخروج.");

        while (true) {
            $message = $this->ask("أدخل الرسالة");

            if (strtolower(trim($message)) === 'exit') {
                break;
            }

            $name = auth()->user()->name;
            $formattedMessage = "$name: $message";

            $encodedMessage = mb_convert_encoding($finalMessage, 'UTF-8');
            $sent = socket_sendto($socket, $encodedMessage, strlen($encodedMessage), 0, $host, $port);

            if ($sent === false) {
                $this->error("❌ فشل في إرسال الرسالة: " . socket_strerror(socket_last_error($socket)));
            } else {
                $this->info("📤 تم إرسال الرسالة: $finalMessage");
            }
        }

        socket_close($socket);
    }
}
