<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return response()->json([
            'message' => 'เปิดใช้ 2FA แล้ว',
            'recovery_codes' => $codes,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, Totp $totp, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);
        if (! $user->two_factor_confirmed_at) {
            return response()->json(['message' => 'บัญชีนี้ยังไม่ได้เปิด 2FA'], 422);
        }
        if (! Hash::check($data['password'], $user->password)
            || ! $this->verifySecondFactor($user, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            return response()->json(['message' => 'รหัสผ่านหรือรหัสยืนยันไม่ถูกต้อง'], 422);
        }

        $codes = $totp->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $audit->recordAuth($request, 'security.two_factor_recovery_regenerated');

        return response()->json([
            'message' => 'สร้างรหัสสำรองชุดใหม่แล้ว',
            'recovery_codes' => $codes,
        ]);
    }

    public function disableTwoFactor(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);
        if (! Hash::check($data['password'], $user->password)
            || ! $this->verifySecondFactor($user, $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            return response()->json(['message' => 'รหัสผ่านหรือรหัสยืนยันไม่ถูกต้อง'], 422);
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
        $audit->recordAuth($request, 'security.two_factor_disabled');

        return response()->json(['message' => 'ปิด 2FA แล้ว']);
    }

    public function confirmReauth(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $hasTwoFactor = (bool) $user->two_factor_confirmed_at;

        $rules = ['password' => ['required', 'string']];
        if ($hasTwoFactor) {
            $rules['code'] = ['nullable', 'digits:6'];
            $rules['recovery_code'] = ['nullable', 'string', 'max:32'];
        }
        $data = $request->validate($rules);

        // Check the password first so a typo there never burns a recovery code.
        $ok = Hash::check($data['password'], $user->password);
        if ($ok && $hasTwoFactor) {
            $ok = $this->verifySecondFactor($user, $data['code'] ?? null, $data['recovery_code'] ?? null);
        }
        if (! $ok) {
            $audit->recordAuth($request, 'security.reauth_failed');

            return response()->json([
                'message' => $hasTwoFactor ? 'รหัสผ่านหรือรหัสยืนยันไม่ถูกต้อง' : 'รหัสผ่านไม่ถูกต้อง',
            ], 422);
        }

        $request->session()->put('auth.password_confirmed_at', time());
        $audit->recordAuth($request, 'security.reauthenticated');

        return response()->json([
            'message' => 'ยืนยันตัวตนแล้ว',
            'valid_for_seconds' => config('credentials.reauth_minutes') * 60,
        ]);
    }

    /**
     * True when the TOTP code is valid, or a recovery code matches — in which
     * case that code is consumed.
     */
    private function verifySecondFactor(User $user, ?string $code, ?string $recoveryCode): bool
    {
        $code = trim((string) $code);
        if ($code !== '') {
            return (bool) $user->two_factor_secret && app(Totp::class)->verify($user->two_factor_secret, $code);
        }

        $recoveryCode = mb_strtolower(trim((string) $recoveryCode));
        if ($recoveryCode === '') {
            return false;
        }
        $remaining = $user->two_factor_recovery_codes ?? [];
        if (! in_array($recoveryCode, $remaining, true)) {
            return false;
        }
        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($remaining, [$recoveryCode])),
        ])->save();

        return true;
    }
}
