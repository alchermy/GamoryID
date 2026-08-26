<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\User;
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
            $shop = Shop::create([
                'name' => $data['shop_name'],
                'slug' => Str::slug($data['shop_name']).'-'.Str::lower(Str::random(5)),
                'status' => SubscriptionStatus::Trialing->value,
                'trial_ends_at' => now()->addDays(30),
                'grace_ends_at' => now()->addDays(44),
            ]);
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'current_shop_id' => $shop->id,
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
                'status' => SubscriptionStatus::Trialing->value,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'grace_ends_at' => now()->addDays(44),
            ]);

            return [$user, $shop];
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        return response()->json(['user' => $this->userPayload($user), 'shop' => $shop], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง']]);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
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

    public function revokeSession(Request $request, string $session): JsonResponse
    {
        $record = DB::table('sessions')->where('id', $session)->where('user_id', $request->user()->id);
        abort_unless($record->exists(), 404);
        $record->delete();
        if (hash_equals($request->session()->getId(), $session)) {
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
            'current_shop_id' => $user->current_shop_id,
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
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
