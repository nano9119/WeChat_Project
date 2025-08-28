<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use App\Services\UdpService;
use App\Http\Resources\MessageResource;
use Illuminate\Support\Facades\Log;

class MessageApiController extends Controller
{
    public function index()
    {
        return MessageResource::collection(
            Message::with('user')->latest()->get()
        );
    }

    public function store(Request $request)
{
    Log::info('🔵 Request received:', $request->all());

    $request->validate([
        'user_id' => 'required|exists:users,id',
        'message' => 'required|string',
    ]);

    $message = Message::create([
        'user_id' => $request->user_id,
        'user_name' => $request->user_name ?? 'unknown',
        'message' => $request->message,
    ]);

    // 🔁 إرسال الرسالة إلى السيرفر UDP مع IP ديناميكي
    try {
        // نحصل على IP السيرفر الديناميكي من Cache
        $serverIp = cache('server_ip', '127.0.0.1'); // fallback للـ localhost
        $serverPort = 9092; // البورت نفسه الموجود في UdpService

        // إنشاء UdpService باستخدام IP الديناميكي
        $udp = new UdpService($serverIp, $serverPort);
        $udp->send($request->message);

        Log::info('📤 Sent to UDP server:', ['message' => $request->message, 'ip' => $serverIp]);
    } catch (\Exception $e) {
        Log::error('❌ فشل الإرسال إلى سيرفر UDP: ' . $e->getMessage());
    }

    return response()->json(['message' => new MessageResource($message)], 201);
}


    public function update(Request $request, Message $message)
    {
        Log::info('✅ Authenticated user:', ['user' => Auth::user()]);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'old_message' => 'required|string',
            'new_message' => 'required|string|max:1000',
        ]);

        if ($message->user_id != $request->user_id) {
            return response()->json([
                'error' => 'غير مصرح لك بتعديل هذه الرسالة'
            ], 403);
        }

        if ($message->message !== $request->old_message) {
            return response()->json([
                'error' => 'نص الرسالة الأصلية لا يتطابق'
            ], 400);
        }

        $message->update([
            'message' => $request->new_message,
        ]);

        return new MessageResource($message);
    }

    public function destroy(Message $message)
    {
        if (auth()->id() !== $message->user_id && auth()) {
            return response()->json(['error' => 'غير مصرح لك بحذف هذه الرسالة'], 403);
        }

        $message->delete();

        return response()->json([
            'message' => '✅ تم حذف الرسالة بنجاح'
        ]);
    }
}
