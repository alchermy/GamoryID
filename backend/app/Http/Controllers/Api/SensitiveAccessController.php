<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SensitiveAccessController extends Controller
{
    public function beginTwoFactor(Request $request, Totp $totp): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            return response()->json(['message' => 'รหัสผ่านไม่ถูกต้อง'], 422);
        }

        $secret = $totp->generateSecret();
        // 2FA columns are not in $fillable — set them past the mass-assignment guard.
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $totp->uri($secret, $request->user()->email),
        ]);
    }

    public function confirmTwoFactor(Request $request, Totp $totp): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        if (! $request->user()->two_factor_secret || ! $totp->verify($request->user()->two_factor_secret, $data['code'])) {
            return response()->json(['message' => 'รหัส 2FA ไม่ถูกต้อง'], 422);
        }
        $request->user()->forceFill(['two_factor_confirmed_at' => now()])->save();

        return response()->json(['message' => 'เปิดใช้ 2FA แล้ว']);
    }

    public function disableTwoFactor(Request $request, Totp $totp, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'digits:6']]);
        if (! Hash::check($data['password'], $user->password)
            || ! $user->two_factor_secret
            || ! $totp->verify($user->two_factor_secret, $data['code'])) {
            return response()->json(['message' => 'รหัสผ่านหรือรหัส 2FA ไม่ถูกต้อง'], 422);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $audit->recordAuth($request, 'security.two_factor_disabled');

        return response()->json(['message' => 'ปิด 2FA แล้ว']);
    }

    public function confirmReauth(Request $request, Totp $totp, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $hasTwoFactor = (bool) $user->two_factor_confirmed_at;

        $rules = ['password' => ['required', 'string']];
        if ($hasTwoFactor) {
            $rules['code'] = ['required', 'digits:6'];
        }
        $data = $request->validate($rules);

        $ok = Hash::check($data['password'], $user->password);
        if ($hasTwoFactor) {
            $ok = $ok && $user->two_factor_secret && $totp->verify($user->two_factor_secret, $data['code']);
        }
        if (! $ok) {
            $audit->recordAuth($request, 'security.reauth_failed');

            return response()->json([
                'message' => $hasTwoFactor ? 'รหัสผ่านหรือรหัส 2FA ไม่ถูกต้อง' : 'รหัสผ่านไม่ถูกต้อง',
            ], 422);
        }

        $request->session()->put('auth.password_confirmed_at', time());
        $audit->recordAuth($request, 'security.reauthenticated');

        return response()->json([
            'message' => 'ยืนยันตัวตนแล้ว',
            'valid_for_seconds' => config('credentials.reauth_minutes') * 60,
        ]);
    }
}
