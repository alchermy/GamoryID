<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopInvitation;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ShopInvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->findActiveInvitation($token)->load('shop:id,name');

        return response()->json(['data' => [
            'shop_name' => $invitation->shop->name,
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
        ]]);
    }

    public function accept(Request $request, string $token, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $invitation = $this->findActiveInvitation($token);

        $user = DB::transaction(function () use ($data, $invitation) {
            $user = User::query()->where('email', $invitation->email)->lockForUpdate()->first();
            if ($user && ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => ['หากมีบัญชีอยู่แล้ว ให้ใช้รหัสผ่านของบัญชีนั้นเพื่อเข้าร่วมร้าน']]);
            }
            if (! $user) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                    'current_shop_id' => $invitation->shop_id,
                ]);
                $user->sendEmailVerificationNotification();
            }
            $user->forceFill(['current_shop_id' => $invitation->shop_id])->save();
            abort_if(ShopMember::query()->where('shop_id', $invitation->shop_id)->where('user_id', $user->id)->exists(), 422, 'คุณเป็นสมาชิกของร้านนี้อยู่แล้ว');
            ShopMember::create([
                'shop_id' => $invitation->shop_id,
                'user_id' => $user->id,
                'role' => 'staff',
                'permissions' => $invitation->permissions,
                'joined_at' => now(),
            ]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::guard('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $request->setUserResolver(fn () => $user);
        $audit->record($request, $invitation->shop, 'team.invitation_accepted', $invitation, ['user_id' => $user->id]);

        return response()->json(['message' => 'เข้าร่วมร้านเรียบร้อยแล้ว']);
    }

    private function findActiveInvitation(string $token): ShopInvitation
    {
        return ShopInvitation::query()
            ->active()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }
}
