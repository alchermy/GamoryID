<?php

namespace App\Services;

use RuntimeException;

class CredentialCipher
{
    public function encrypt(array $credentials): array
    {
        $nonce = random_bytes(12);
        $plain = json_encode($credentials, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $tag = '';
        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'gamoryid-credentials-v1',
            16,
        );
        if ($cipher === false) {
            throw new RuntimeException('Credential encryption failed.');
        }

        return ['payload' => base64_encode($nonce.$tag.$cipher), 'key_version' => 1];
    }

    public function decrypt(string $payload): array
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException('Credential payload is invalid.');
        }

        $nonceLength = 12;
        $tagLength = 16;
        if (strlen($decoded) <= $nonceLength + $tagLength) {
            throw new RuntimeException('Credential payload is invalid.');
        }
        $plain = openssl_decrypt(
            substr($decoded, $nonceLength + $tagLength),
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            substr($decoded, 0, $nonceLength),
            substr($decoded, $nonceLength, $tagLength),
            'gamoryid-credentials-v1',
        );

        if ($plain === false) {
            throw new RuntimeException('Credential payload authentication failed.');
        }

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    private function key(): string
    {
        $configured = config('credentials.keys.v1');
        if ($configured) {
            $decoded = base64_decode($configured, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        if (app()->environment(['local', 'testing'])) {
            return hash('sha256', (string) config('app.key'), true);
        }

        throw new RuntimeException('CREDENTIAL_ENCRYPTION_KEY_V1 is required in production.');
    }
}
