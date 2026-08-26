<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CredentialCipher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $starter = SubscriptionPlan::updateOrCreate(['code' => 'starter'], ['name' => 'Starter', 'active_inventory_limit' => 1000, 'member_limit' => 3, 'price_thb' => 299, 'duration_days' => 30, 'is_active' => true]);
        SubscriptionPlan::updateOrCreate(['code' => 'growth'], ['name' => 'Growth', 'active_inventory_limit' => 5000, 'member_limit' => 8, 'price_thb' => 699, 'duration_days' => 30, 'is_active' => true]);

        $shop = Shop::updateOrCreate(['slug' => 'nexus-store'], ['name' => 'Nexus Store', 'status' => 'trialing', 'trial_ends_at' => now()->addDays(24), 'grace_ends_at' => now()->addDays(38)]);
        $owner = User::updateOrCreate(['email' => 'owner@gamoryid.local'], ['name' => 'พีท เจ้าของร้าน', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::updateOrCreate(['shop_id' => $shop->id, 'user_id' => $owner->id], ['role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        Subscription::firstOrCreate(['shop_id' => $shop->id, 'status' => 'trialing'], ['subscription_plan_id' => $starter->id, 'starts_at' => now()->subDays(6), 'ends_at' => now()->addDays(24), 'grace_ends_at' => now()->addDays(38)]);

        $samples = [
            ['23DX5', 'Reaver Collection · Vandal', 'AP', 'Ascendant 2', 238, 67, 6200, 8900, 'available'],
            ['8KM4R', 'Prime 2.0 · Phantom', 'TH', 'Diamond 3', 191, 49, 4400, 6900, 'reserved'],
            ['Q7N2P', 'Champions 2023 · Vandal', 'AP', 'Immortal 1', 306, 82, 9900, 13900, 'available'],
            ['4WT9C', 'Kuronami · Bundle', 'SG', 'Platinum 2', 144, 35, 2700, 4500, 'available'],
            ['M6J3X', 'RGX 11z Pro · Operator', 'AP', 'Diamond 1', 217, 55, 5200, 7600, 'sold'],
            ['9RA5K', 'Prelude to Chaos · Vandal', 'TH', 'Ascendant 1', 262, 71, 6800, 9600, 'available'],
            ['C3Y8N', 'Gaia’s Vengeance · Bundle', 'AP', 'Gold 3', 128, 42, 3100, 4900, 'reserved'],
            ['H5P7D', 'Neo Frontier · Sheriff', 'SG', 'Diamond 2', 174, 38, 3900, 6100, 'available'],
        ];
        $cipher = app(CredentialCipher::class);
        foreach ($samples as $index => [$tag, $title, $region, $rank, $level, $skins, $cost, $price, $status]) {
            $item = InventoryItem::updateOrCreate(['tag' => $tag], [
                'shop_id' => $shop->id, 'created_by' => $owner->id, 'title' => $title, 'region' => $region,
                'rank' => $rank, 'level' => $level, 'skin_count' => $skins, 'cost' => $cost, 'list_price' => $price,
                'status' => $status, 'description' => 'พร้อมส่งมอบ มีรายละเอียดครบ ตรวจสอบโดยทีมร้านแล้ว',
                'custom_values' => ['email_changeable' => $index % 2 === 0],
            ]);
            if ($index < 3) {
                $encrypted = $cipher->encrypt(['username' => "demo{$index}@example.test", 'password' => Str::password(16)]);
                InventoryCredential::updateOrCreate(['inventory_item_id' => $item->id], ['encrypted_payload' => $encrypted['payload'], 'key_version' => $encrypted['key_version']]);
            }
        }
        if (! ActivityLog::where('shop_id', $shop->id)->exists()) {
            ActivityLog::insert([
                ['shop_id' => $shop->id, 'user_id' => $owner->id, 'event' => 'inventory.created', 'subject_type' => null, 'subject_id' => null, 'metadata' => json_encode(['tag' => '#23DX5']), 'created_at' => now()->subMinutes(8)],
                ['shop_id' => $shop->id, 'user_id' => $owner->id, 'event' => 'inventory.reserved', 'subject_type' => null, 'subject_id' => null, 'metadata' => json_encode(['tag' => '#8KM4R']), 'created_at' => now()->subMinutes(24)],
                ['shop_id' => $shop->id, 'user_id' => $owner->id, 'event' => 'inventory.sold', 'subject_type' => null, 'subject_id' => null, 'metadata' => json_encode(['tag' => '#M6J3X']), 'created_at' => now()->subHours(2)],
            ]);
        }

        User::updateOrCreate(['email' => 'admin@gamoryid.local'], ['name' => 'GamoryID Admin', 'password' => 'password'])
            ->forceFill(['is_super_admin' => true])->save();
    }
}
