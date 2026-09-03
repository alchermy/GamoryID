<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks mutating requests when the signed-in user has not accepted the
 * currently in-force Terms of Service version. Read-only requests (GET/HEAD/
 * OPTIONS) still pass so the SPA can render and the user can review the new
 * terms and export their data. The merchant SPA turns the 409 + code into a
 * full-screen re-consent screen; it also learns the state ahead of time from
 * the `terms_current` flag in the /auth/me payload.
 */
class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Safe methods, super admins (mirrors CurrentShop::from's carve-out),
        // and users already on the current version all pass straight through.
        if ($request->isMethodSafe()
            || $user === null
            || $user->is_super_admin
            || $user->hasAcceptedCurrentTerms()
        ) {
            return $next($request);
        }

        return response()->json([
            'message' => 'ข้อกำหนดการใช้บริการมีการปรับปรุง กรุณายอมรับฉบับใหม่ก่อนทำรายการต่อ',
            'code' => 'TERMS_REACCEPT_REQUIRED',
        ], 409);
    }
}
