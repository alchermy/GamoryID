<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShopPermission;
use App\Http\Controllers\Controller;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use App\Services\PlanEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request, CurrentShop $currentShop, AuditLogger $audit, PlanEntitlements $entitlements)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(ShopPermission::class)],
        ], [
            'email.unique' => 'อีเมลนี้มีบัญชีอยู่แล้ว กรุณาใช้อีเมลอื่น',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร',
            'password.confirmed' => 'การยืนยันรหัสผ่านไม่ตรงกัน',
        ]);

        $entitlements->ensureMemberCapacity($shop);

        $member = DB::transaction(function () use ($shop, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'email_verified_at' => now(),
                'current_shop_id' => $shop->id,
            ]);

            return ShopMember::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role' => 'staff',
                'permissions' => $data['permissions'] ?? [],
                'joined_at' => now(),
            ]);
        });

        $audit->record($request, $shop, 'team.member_created', $member, ['email' => $data['email']]);

        return response()->json(['data' => $member->load('user:id,name,email')], 201);
    }

    public function update(Request $request, int $member, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'permissions' => ['required', 'array'],
            'permissions.*' => [Rule::enum(ShopPermission::class)],
        ]);
        $record = ShopMember::where('shop_id', $shop->id)->where('role', 'staff')->with('user')->findOrFail($member);
        $record->update(['permissions' => $data['permissions']]);
        if (array_key_exists('name', $data)) {
            $record->user->update(['name' => $data['name']]);
        }
        $audit->record($request, $shop, 'team.permissions_updated', $record);

        return response()->json(['data' => $record->fresh('user')]);
    }

    public function resetPassword(Request $request, int $member, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร',
            'password.confirmed' => 'การยืนยันรหัสผ่านไม่ตรงกัน',
        ]);
        $record = ShopMember::where('shop_id', $shop->id)->where('role', 'staff')->with('user')->findOrFail($member);
        $record->user->update(['password' => $data['password']]);
        DB::table('sessions')->where('user_id', $record->user_id)->delete();
        $audit->record($request, $shop, 'team.member_password_reset', $record);

        return response()->json(['message' => 'ตั้งรหัสผ่านใหม่แล้ว']);
    }

    public function destroy(Request $request, int $member, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $record = ShopMember::where('shop_id', $shop->id)->where('role', 'staff')->findOrFail($member);
        $audit->record($request, $shop, 'team.member_removed', $record, ['user_id' => $record->user_id]);
        $record->delete();

        return response()->json(['message' => 'นำสมาชิกออกแล้ว']);
    }
}
