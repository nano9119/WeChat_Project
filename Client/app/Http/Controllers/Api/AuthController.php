<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'client',
    ]);

    // تسجيل الدخول تلقائي بعد إنشاء الحساب
    Auth::login($user);

    return redirect()->route('wait');
}

    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return back()->withErrors(['email' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
    }

    Auth::login($user);

    return redirect()->route('wait');
}

    public function logout(Request $request)
    {
        $user = $request->user();

        // حذف التوكن الحالي
        $user->currentAccessToken()->delete();

        // حذف حساب المستخدم إذا أردت (اختياري)
        // $user->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'تم تسجيل الخروج بنجاح.']);
        }

        auth()->logout();
        return redirect('/login');
    }
}
