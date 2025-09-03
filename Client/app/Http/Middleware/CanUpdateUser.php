<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CanUpdateUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $targetUserId = $request->route('user'); // اسم الـ parameter في Route

        // إذا الأدمين أو صاحب الحساب
        if ($user->is_admin || $user->id == $targetUserId) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
