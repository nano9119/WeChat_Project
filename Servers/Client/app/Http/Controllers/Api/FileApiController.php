<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
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

       // احصل على الملف الجديد
        $file = $request->file('file');

        // احفظ الملف الجديد في مجلد 'uploads' أو حسب اختيارك
        $newFilePath = $file->store('uploads');

        // احصل على مسار الملف القديم من الريكوست
        $oldFilePath = $request->input('old_file_path');

        // لو تريد تحذف الملف القديم (تأكد أنه موجود)
        if ($oldFilePath && Storage::exists($oldFilePath)) {
            Storage::delete($oldFilePath);
        }

        // ترجع نتيجة للعميل
        return response()->json([
            'message' => 'File updated successfully',
            'new_file_path' => $newFilePath,
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
        $server = '127.0.0.1';
        $port = 9090;

        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($sock === false) {
            \Log::error('❌ فشل في إنشاء اتصال TCP: ' . socket_strerror(socket_last_error()));
            return;
        }

        if (!socket_connect($sock, $server, $port)) {
            \Log::error('❌ فشل في الاتصال بسيرفر TCP: ' . socket_strerror(socket_last_error($sock)));
            return;
        }

        socket_send($sock, "[METADATA] " . $filename, 1024, 0);

        $file = fopen($filePath, 'rb');
        while (!feof($file)) {
            $chunk = fread($file, 4096);
            socket_send($sock, $chunk, strlen($chunk), 0);
        }
        fclose($file);

        socket_send($sock, "[EOF]", 1024, 0);
        socket_close($sock);
    }
}
