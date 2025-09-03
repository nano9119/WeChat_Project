<?php
namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\File;
use Illuminate\Http\Request;
use App\Services\UdpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function index()
{
    $messages = Message::with('user')->get();
    $files = File::with('user')->get();

    // دمج الكل حسب created_at
    $items = $messages->concat($files)->sortBy('created_at')->values();

    return view('chat', compact('items'));
}


    public function sendMessage(Request $request)
{
    $request->validate([
        'message' => 'required|string|max:255',
    ]);

    $user = Auth::user();

    if (!$user) {
        return redirect()->route('login')->withErrors(['message' => 'يجب تسجيل الدخول أولاً']);
    }

    Message::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'message' => $request->message,
    ]);

    // إرسال عبر UDP
    try {
        $udp = new UdpService();
        $udp->sendMessage($user->name . ': ' . $request->message);
    } catch (\Exception $e) {
        Log::error('❌ فشل إرسال الرسالة عبر UDP: ' . $e->getMessage());
    }

    return redirect()->route('chat');
}


    public function sendFile(Request $request)
    {
        $request->validate([
            'file_path' => 'required|file|max:204800', // الحد الأقصى 200MB
        ]);

        $user = Auth::user();
        $file = $request->file('file_path');
        $extension = strtolower($file->getClientOriginalExtension());

        $type = in_array($extension, ['jpg','jpeg','png','gif','webp']) ? 'image' :
                (in_array($extension, ['mp4', 'mov', 'avi', 'mkv']) ? 'video' : 'audio');

        try {
            // إنشاء سوكت TCP
            $tcpSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$tcpSocket) {
                throw new \Exception("socket_create failed: " . socket_strerror(socket_last_error()));
            }

            // الاتصال بالسيرفر المحلي
            if (!@socket_connect($tcpSocket, '127.0.0.1', 9090)) {
                throw new \Exception("socket_connect failed: " . socket_strerror(socket_last_error($tcpSocket)));
            }

            $filename = time() . "_" . $file->getClientOriginalName();
            $fileData = file_get_contents($file->getRealPath());

            // إرسال metadata أولًا
            $meta = "[METADATA] {$filename}\n";
            socket_write($tcpSocket, $meta, strlen($meta));

            // إرسال الملف على دفعات لتجنب فقدان البيانات
            $chunkSize = 8192; // 8KB لكل مرة
            $offset = 0;
            $fileLength = strlen($fileData);
            while ($offset < $fileLength) {
                $sent = socket_write($tcpSocket, substr($fileData, $offset, $chunkSize));
                if ($sent === false) {
                    throw new \Exception("فشل إرسال جزء من الملف: " . socket_strerror(socket_last_error($tcpSocket)));
                }
                $offset += $sent;
            }

            // إرسال EOF
            socket_write($tcpSocket, "[EOF]\n", strlen("[EOF]\n"));
            socket_close($tcpSocket);

            // تخزين الملف في قاعدة البيانات
            File::create([
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'file_path' => str_replace('public/', '', $file->store('public/received_files')),
                'type'      => $type,
            ]);

            return redirect()->route('chat')->with('success', '✅ تم إرسال الملف بنجاح.');
        } catch (\Exception $e) {
            Log::error("❌ فشل إرسال الملف عبر TCP: " . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
{
    $message = Message::findOrFail($id);

    if ($message->user_id !== auth()->id()) {
        return response()->json(['error' => 'غير مصرح'], 403);
    }

    $request->validate([
        'message' => 'required|string|max:1000',
    ]);

    $message->message = $request->message;
    $message->save();

    return response()->json(['success' => true]);
}

   public function destroy($id)
    {
    $message = Message::findOrFail($id);

    if ($message->user_id !== auth()->id()) {
        return response()->json(['error' => 'غير مصرح'], 403);
    }

    $message->delete();

    return response()->json(['success' => true]);
    }

public function updateFile(Request $request, $id)
{
    $request->validate([
        'file' => 'required|file|max:20480',
        'old_file_path' => 'required|string',
    ]);

    $fileRecord = File::findOrFail($id);

    if ($fileRecord->user_id !== auth()->id()) {
        return response()->json(['error' => 'غير مصرح'], 403);
    }

    Storage::disk('public')->delete($request->old_file_path);

    $newFile = $request->file('file');
    $newPath = $newFile->store('uploads', 'public');

    $fileRecord->file_path = $newPath;
    $fileRecord->save();

    return response()->json(['success' => true, 'new_path' => $newPath]);
}



public function deleteFile($id)
{
    $file = File::findOrFail($id);

    // تسجيل محاولة الحذف
    \Log::info('🧪 محاولة حذف الملف رقم: ' . $id . ' بواسطة المستخدم: ' . auth()->id());

    // السماح فقط لصاحب الملف
    if ($file->user_id !== auth()->id()) {
        \Log::warning('🚫 رفض حذف الملف رقم: ' . $id . ' - المستخدم غير مصرح');
        return response()->json(['error' => 'غير مصرح'], 403);
    }

    // حذف الملف من التخزين
    Storage::disk('public')->delete($file->file_path);

    // حذف السجل من قاعدة البيانات
    $file->delete();

    \Log::info('✅ تم حذف الملف رقم: ' . $id . ' بنجاح بواسطة المستخدم: ' . auth()->id());

    return response()->json(['success' => true]);
}


}
