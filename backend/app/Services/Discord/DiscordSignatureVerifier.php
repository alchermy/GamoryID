<?php

namespace App\Services\Discord;

class DiscordSignatureVerifier
{
    public function __construct(private readonly DiscordApiClient $client) {}

    public function verify(?string $signature, ?string $timestamp, string $body): bool
    {
        if ($this->client->isTestMode()) {
            return true;
        }

        if (! $signature || ! $timestamp || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $publicKey = @hex2bin((string) config('services.discord.public_key'));
        $signatureBytes = @hex2bin($signature);

        if ($publicKey === false || $signatureBytes === false || ! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signatureBytes, $timestamp.$body, $publicKey);
    }
}
