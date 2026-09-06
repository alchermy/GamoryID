<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSensitiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only checks that the actor re-confirmed their identity recently. WHAT
        // counts as a valid re-confirm (password, or password + 2FA code) is
        // decided by SensitiveAccessController::confirmReauth based on whether
        // the account has 2FA turned on.
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        $fresh = $confirmedAt >= now()->subMinutes(config('credentials.reauth_minutes'))->timestamp;

        if (! $fresh) {
            return response()->json([
                'message' => 'กรุณายืนยันตัวตนอีกครั้งก่อนดูข้อมูลลับ',
                'code' => 'SENSITIVE_REAUTH_REQUIRED',
            ], 428);
        }

        return $next($request);
    }
}
