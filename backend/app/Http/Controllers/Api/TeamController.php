<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShopPermission;
use App\Http\Controllers\Controller;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
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

    public function store(Request $request, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $this->validateMember($request);
        $subscription = $shop->subscriptions()->latest()->with('plan')->first();
        $limit = $subscription?->plan?->member_limit ?? 3;
        abort_if(ShopMember::where('shop_id', $shop->id)->count() >= $limit, 422, 'จำนวนสมาชิกเต็มตามแพ็กเกจ');

        $member = DB::transaction(function () use ($data, $shop) {
            $user = User::firstOrCreate(
                ['email' => Str::lower($data['email'])],
                ['name' => $data['name'], 'password' => Str::password(24), 'current_shop_id' => $shop->id],
            );

            return ShopMember::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'role' => 'staff',
                'permissions' => $data['permissions'],
                'joined_at' => now(),
            ]);
        });
        $audit->record($request, $shop, 'team.member_added', $member, ['email' => $data['email']]);

        return response()->json(['data' => $member->load('user:id,name,email')], 201);
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

    private function validateMember(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'permissions' => ['required', 'array'],
            'permissions.*' => [Rule::enum(ShopPermission::class)],
        ]);
    }
}
