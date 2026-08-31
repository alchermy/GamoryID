<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShopPermission;
use App\Http\Controllers\Controller;
use App\Models\ShopInvitation;
use App\Models\ShopMember;
use App\Models\User;
use App\Notifications\ShopInvitationNotification;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $members = ShopMember::query()->where('shop_id', $shop->id)->with('user:id,name,email')->latest()->get();

        return response()->json(['data' => $members]);
    }

    public function invitations(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $invitations = ShopInvitation::query()
            ->where('shop_id', $shop->id)
            ->active()
            ->with('inviter:id,name')
            ->latest()
            ->get();

        return response()->json(['data' => $invitations]);
    }

    public function store(Request $request, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $this->validateMember($request);
        $subscription = $shop->subscriptions()->latest()->with('plan')->first();
        $limit = $subscription?->plan?->member_limit ?? 3;
        $memberCount = ShopMember::where('shop_id', $shop->id)->count();
        $existingInvitation = ShopInvitation::query()
            ->where('shop_id', $shop->id)
            ->where('email', Str::lower($data['email']))
            ->active()
            ->first();
        $inviteCount = ShopInvitation::query()
            ->where('shop_id', $shop->id)
            ->active()
            ->when($existingInvitation, fn ($query) => $query->whereKeyNot($existingInvitation->id))
            ->count();
        abort_if($memberCount + $inviteCount >= $limit, 422, 'จำนวนสมาชิกและคำเชิญเต็มตามแพ็กเกจ');
        $existingUser = User::query()->where('email', Str::lower($data['email']))->first();
        abort_if($existingUser && ShopMember::where('shop_id', $shop->id)->where('user_id', $existingUser->id)->exists(), 422, 'อีเมลนี้เป็นสมาชิกของร้านอยู่แล้ว');

        $existingInvitation?->update(['revoked_at' => now()]);
        $token = Str::random(64);
        $invitation = ShopInvitation::create([
            'shop_id' => $shop->id,
            'invited_by' => $request->user()->id,
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'permissions' => $data['permissions'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        $audit->record($request, $shop, 'team.invitation_created', $invitation, ['email' => $data['email']]);
        $inviteUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/invite/'.$token;
        Notification::route('mail', $invitation->email)->notify(new ShopInvitationNotification($invitation->load('shop:id,name'), $inviteUrl));

        return response()->json([
            'data' => $invitation->load('inviter:id,name'),
            'invite_url' => $inviteUrl,
        ], 201);
    }

    public function update(Request $request, int $member, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate(['permissions' => ['required', 'array'], 'permissions.*' => [Rule::enum(ShopPermission::class)]]);
        $record = ShopMember::where('shop_id', $shop->id)->where('role', 'staff')->findOrFail($member);
        $record->update(['permissions' => $data['permissions']]);
        $audit->record($request, $shop, 'team.permissions_updated', $record);

        return response()->json(['data' => $record]);
    }

    public function destroy(Request $request, int $member, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $record = ShopMember::where('shop_id', $shop->id)->where('role', 'staff')->findOrFail($member);
        $audit->record($request, $shop, 'team.member_removed', $record, ['user_id' => $record->user_id]);
        $record->delete();

        return response()->json(['message' => 'นำสมาชิกออกแล้ว']);
    }

    public function revokeInvitation(Request $request, int $invitation, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $record = ShopInvitation::query()->where('shop_id', $shop->id)->active()->findOrFail($invitation);
        $record->update(['revoked_at' => now()]);
        $audit->record($request, $shop, 'team.invitation_revoked', $record, ['email' => $record->email]);

        return response()->json(['message' => 'ยกเลิกคำเชิญแล้ว']);
    }

    private function validateMember(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::enum(ShopPermission::class)],
        ]);
    }
}
