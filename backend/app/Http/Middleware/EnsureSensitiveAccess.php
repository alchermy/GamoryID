<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSensitiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        $fresh = $confirmedAt >= now()->subMinutes(config('credentials.reauth_minutes'))->timestamp;

        if (! $user?->two_factor_confirmed_at || ! $fresh) {
            return response()->json([
                'message' => 'กรุณายืนยันรหัสผ่านและ 2FA อีกครั้งก่อนดูข้อมูลลับ',
                'code' => 'SENSITIVE_REAUTH_REQUIRED',
            ], 428);
        }

        return $next($request);
    }
}
