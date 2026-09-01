<?php

namespace App\Services\Discord;

use App\Models\DiscordLinkCode;
use App\Models\DiscordSetupCode;
use App\Models\Shop;
use App\Models\User;

class DiscordCodeService
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function issueSetupCode(Shop $shop, User $user): array
    {
        DiscordSetupCode::query()
            ->where('shop_id', $shop->id)
            ->whereNull('used_at')
            ->delete();

        $code = $this->generate();
        $record = DiscordSetupCode::create([
            'shop_id' => $shop->id,
            'created_by' => $user->id,
            'token_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return ['code' => $code, 'record' => $record];
    }

    public function issueLinkCode(Shop $shop, User $user): array
    {
        DiscordLinkCode::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $code = $this->generate();
        $record = DiscordLinkCode::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return ['code' => $code, 'record' => $record];
    }

    private function generate(int $length = 8): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($index = 0; $index < $length; $index++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
