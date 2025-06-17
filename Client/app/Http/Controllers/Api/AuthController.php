<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate
        ([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'password_confirmation' => 'required'
            ], 
            [
            'password.confirmed' => 'كلمة المرور وتأكيدها غير متطابقتين.',
            'password_confirmation.required' => 'حقل تأكيد كلمة المرور مطلوب.',
         ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'password_confirmation' =>  $data[ 'password_confirmation']
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api_token')->plainTextToken
        ]);
    }

    public function login(Request $request)
    {
         
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['المعلومات غير صحيحة.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api_token')->plainTextToken
        ],200);
    }

    public function logout(Request $request)
{
    $user = $request->user();

    // حذف التوكن الحالي
    $user->currentAccessToken()->delete();

    // حذف حساب المستخدم
    $user->delete();

    return response()->json(['message' => 'تم حذف الحساب وتسجيل الخروج بنجاح.']);
}

}
