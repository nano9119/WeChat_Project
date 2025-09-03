<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\File;

class UserApiController extends Controller
{
    // ✅ إرجاع قائمة المستخدمين (للأدمين فقط)
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return UserResource::collection(User::all());
    }

    // ✅ عرض مستخدم واحد (يمكن لأي مستخدم رؤية بياناته الخاصة أو الأدمين أي مستخدم)
    public function show($id)
    {
        $user = User::findOrFail($id);

        if (auth()->user()->role !== 'admin' && auth()->id() !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new UserResource($user);
    }

    // ✅ إنشاء مستخدم (يمكن للأدمين فقط)
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:client,admin',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return new UserResource($user);
    }

    // ✅ تعديل مستخدم
  public function update(Request $request, $id)
{
    $user = User::findOrFail($id);
    $authUser = auth()->user();

    // السماح فقط للأدمين أو للمستخدم نفسه
    if (!$authUser || ($authUser->role !== 'admin' && $authUser->id !== $user->id)) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // التحقق من البيانات
    $validated = $request->validate([
        'name'     => 'sometimes|required|string',
        'email'    => 'sometimes|required|email|unique:users,email,' . $user->id,
        'password' => 'nullable|string|min:6',
        'role'     => 'sometimes|required|in:client,admin',
    ]);

    // حماية تعديل الدور: فقط الأدمين يمكنه تغييره
    if (isset($validated['role']) && $authUser->role !== 'admin') {
        unset($validated['role']);
    }

    // تحديث البيانات
    if (isset($validated['password'])) {
        $validated['password'] = Hash::make($validated['password']);
    }

    $user->update($validated);

    // ✅ حدث الرسائل والملفات بعد تحديث المستخدم

    Message::where('user_id', $user->id)
        ->update(['user_name' => $user->name]);

    File::where('user_id', $user->id)
        ->update(['user_name' => $user->name]);

    return new UserResource($user);
}


    // ✅ حذف مستخدم (للأدمين فقط)
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
