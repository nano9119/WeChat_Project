<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;


class UserApiController extends Controller
{
    // ✅ إرجاع قائمة المستخدمين
    public function index()
    {
        return UserResource::collection(User::all());
    }

    // ✅ عرض مستخدم واحد
    public function show($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }

    // ✅ إنشاء مستخدم
    public function store(Request $request)
    {
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

        $validated = $request->validate([
            'name'     => 'sometimes|required|string',
            'email'    => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role'     => 'sometimes|required|in:client,admin',
        ]);

        $user->update([
            'name'     => $validated['name']     ?? $user->name,
            'email'    => $validated['email']    ?? $user->email,
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : $user->password,
            'role'     => $validated['role']     ?? $user->role,
        ]);

        return new UserResource($user);
    }

    // ✅ حذف مستخدم
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
