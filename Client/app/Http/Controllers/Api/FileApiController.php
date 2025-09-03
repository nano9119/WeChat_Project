<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Http\Requests\FileRequest;
use App\Http\Resources\FileResource;

class FileApiController extends Controller
{
    public function index()
    {
        return FileResource::collection(File::with('user')->latest()->get());
    }

    public function store(FileRequest $request)
    {
        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('uploads', 'public');

        $type = match (true) {
            str_starts_with($uploadedFile->getMimeType(), 'image') => 'image',
            str_starts_with($uploadedFile->getMimeType(), 'video') => 'video',
            str_starts_with($uploadedFile->getMimeType(), 'audio') => 'audio',
            default => 'unknown',
        };

        $file = File::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'file_path' => $path,
            'type' => $type,
        ]);

        $this->sendToTcpServer(storage_path("app/public/{$path}"), $uploadedFile->getClientOriginalName());

        return new FileResource($file);
    }

public function update(Request $request, $id)
{
    $request->validate([
        'file' => 'required|file',
        'old_file_path' => 'required|string',
    ]);

    // احصل على السجل من قاعدة البيانات
    $fileRecord = File::findOrFail($id);

    // احصل على الملف الجديد
    $file = $request->file('file');
    $newFilePath = $file->store('uploads', 'public'); // storage/app/public/uploads

    // حذف الملف القديم من التخزين إذا موجود
    $oldFilePath = $request->input('old_file_path');
    if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
        Storage::disk('public')->delete($oldFilePath);
    }

    // تحديث قاعدة البيانات
    $fileRecord->file_path = $newFilePath;

    // تحديد type تلقائيًا حسب امتداد الملف
    $extension = strtolower($file->getClientOriginalExtension());
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $fileRecord->type = 'image';
    } elseif (in_array($extension, ['mp4', 'mov', 'avi', 'mkv'])) {
        $fileRecord->type = 'video';
    } elseif (in_array($extension, ['mp3', 'wav', 'ogg', 'm4a','opus'])) {
        $fileRecord->type = 'audio';
    } else {
        $fileRecord->type = 'audio'; // افتراضي إذا امتداد غير معروف
    }

    $fileRecord->save();

    return response()->json([
        'message' => 'File updated successfully',
        'new_file_path' => $newFilePath,
        'file_record' => $fileRecord,
    ]);
}

    public function destroy($id)
    {
        $file = File::findOrFail($id);

        // احذف الملف من التخزين إن وجد
        Storage::disk('public')->delete($file->file_path);

        // احذف من قاعدة البيانات
        $file->delete();

        return response()->json(['message' => 'File deleted successfully.']);
    }

    protected function sendToTcpServer($filePath, $filename)
    {
    // نحصل على IP السيرفر الديناميكي من الـ Cache
    $serverIp = cache('server_ip', '127.0.0.1'); // fallback للـ localhost
    $serverPort = 9090; // البورت نفسه كما كان

    // إنشاء TcpService باستخدام IP الديناميكي
    $tcpService = new \App\Services\TcpService($serverIp, $serverPort);

    // إرسال الملف
    $success = $tcpService->sendFile($filePath, $filename);

    if (!$success) {
        \Log::error("❌ فشل إرسال الملف إلى TCP Server: $filename");
    }
    }

    // --- واجهة Blade: صفحة الدردشة ---
    public function chat()
    {
        $messages = Message::with('user')->orderBy('created_at', 'asc')->get();
        $files = File::with('user')->orderBy('created_at', 'asc')->get();
        return view('chat', compact('messages','files'));
    }

}
