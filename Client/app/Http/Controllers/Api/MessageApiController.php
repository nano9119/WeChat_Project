<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\File;
use Illuminate\Support\Facades\Auth;
use App\Services\UdpService;
use App\Http\Resources\MessageResource;
use Illuminate\Support\Facades\Log;

class MessageApiController extends Controller
{
    // --- API: جلب كل الرسائل ---
    public function index()
    {
        return MessageResource::collection(
            Message::with('user')->orderBy('created_at', 'asc')->get()
        );
    }

    // --- API: إرسال رسالة جديدة ---
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

        // إرسال الرسالة للسيرفر UDP
        try {
            $serverIp = cache('server_ip', '127.0.0.1'); 
            $serverPort = 9092;

            $udp = new UdpService($serverIp, $serverPort);
            $udp->send($request->message);

            Log::info('📤 Sent to UDP server:', ['message' => $request->message, 'ip' => $serverIp]);
        } catch (\Exception $e) {
            Log::error('❌ فشل الإرسال إلى سيرفر UDP: ' . $e->getMessage());
        }

        return response()->json(['message' => new MessageResource($message)], 201);
    }

    // --- API: تعديل رسالة ---
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

    // --- API: حذف رسالة ---
    public function destroy(Message $message)
    {
        if (auth()->id() !== $message->user_id) {
            return response()->json(['error' => 'غير مصرح لك بحذف هذه الرسالة'], 403);
        }

        $message->delete();

        return response()->json([
            'message' => '✅ تم حذف الرسالة بنجاح'
        ]);
    }

    // --- واجهة Blade: صفحة الدردشة ---
    public function chat()
    {
        $messages = Message::with('user')->orderBy('created_at', 'asc')->get();
        $files = File::with('user')->orderBy('created_at', 'asc')->get();
        return view('chat', compact('messages','files'));
    }
}
