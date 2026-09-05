<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$user, $shop] = DB::transaction(function () use ($data) {
            // Trial mirrors the Growth tier so new owners experience the full
            // feature set for 14 days, then fall back to Free entitlements.
            $trialPlan = SubscriptionPlan::query()
                ->whereIn('code', ['growth', 'starter'])
                ->where('is_active', true)
                ->orderByRaw("code = 'growth' desc")
                ->firstOrFail();
            $trialEndsAt = now()->addDays(14);
            $shop = Shop::create([
                'name' => $data['shop_name'],
                'slug' => Str::slug($data['shop_name']).'-'.Str::lower(Str::random(5)),
                'status' => SubscriptionStatus::Trialing->value,
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => $trialEndsAt,
            ]);
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'current_shop_id' => $shop->id,
                'terms_accepted_at' => now(),
                'terms_version' => config('legal.terms_version'),
            ]);
            ShopMember::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'permissions' => [],
                'joined_at' => now(),
            ]);
            Subscription::create([
                'shop_id' => $shop->id,
                'subscription_plan_id' => $trialPlan->id,
                'status' => SubscriptionStatus::Trialing->value,
                'starts_at' => now(),
                'ends_at' => $trialEndsAt,
                'grace_ends_at' => $trialEndsAt,
            ]);

            return [$user, $shop];
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        // A mail-provider hiccup must not 500 the whole signup and strand a
        // half-created account — the owner can re-send the verification later.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }
        app(AuditLogger::class)->recordAuth($request, 'auth.registered', ['role' => 'owner']);

        return response()->json(['user' => $this->userPayload($user), 'shop' => $shop], 201);
    }

    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง']]);
        }
        $request->session()->regenerate();
        $audit->recordAuth($request, 'auth.logged_in');

        return response()->json(['user' => $this->userPayload(Auth::guard('web')->user())]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function acceptTerms(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $version = config('legal.terms_version');
        $user->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => $version,
        ])->save();
        $audit->recordAuth($request, 'auth.terms_accepted', ['version' => $version]);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $audit->recordAuth($request, 'auth.logged_out');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ออกจากระบบแล้ว']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()->map(fn ($session) => [
            'id' => $session->id,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'last_activity' => $session->last_activity,
            'is_current' => hash_equals($request->session()->getId(), $session->id),
        ]);

        return response()->json(['data' => $sessions]);
    }

    public function revokeSession(Request $request, string $session, AuditLogger $audit): JsonResponse
    {
        $record = DB::table('sessions')->where('id', $session)->where('user_id', $request->user()->id);
        abort_unless($record->exists(), 404);
        $isCurrent = hash_equals($request->session()->getId(), $session);
        $audit->recordAuth($request, 'auth.session_revoked', ['current_device' => $isCurrent]);
        $record->delete();
        if ($isCurrent) {
            Auth::guard('web')->logout();
        }

        return response()->json(['message' => 'นำอุปกรณ์ออกจากระบบแล้ว']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'current_shop_id' => $user->current_shop_id,
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            'terms_current' => $user->hasAcceptedCurrentTerms(),
            'shops' => $user->shops()->get()->map(fn ($shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'status' => $shop->status,
                'role' => $shop->pivot->role,
                'permissions' => json_decode($shop->pivot->permissions ?? '[]', true) ?: [],
            ]),
        ];
    }
}
