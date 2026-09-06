<?php

namespace App\Services;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * One-time 2FA recovery codes, formatted "xxxxx-xxxxx" (lowercase, no
     * ambiguous characters).
     *
     * @return array<int, string>
     */
    public function recoveryCodes(int $count = 8): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $pick = fn (int $len) => implode('', array_map(
            fn () => $alphabet[random_int(0, strlen($alphabet) - 1)],
            range(1, $len),
        ));

        return array_map(fn () => $pick(5).'-'.$pick(5), range(1, $count));
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $counter = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function uri(string $secret, string $email): string
    {
        $label = rawurlencode('GamoryID:'.$email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer=GamoryID&digits=6&period=30";
    }

    /**
     * The valid 6-digit code for a secret right now. Test-only helper — there is
     * no way to bypass verify().
     */
    public function currentCode(string $secret): string
    {
        return $this->code($secret, (int) floor(time() / 30));
    }

    private function code(string $secret, int $counter): string
    {
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->decodeBase32($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $input): string
    {
        $bits = '';
        foreach (str_split(strtoupper($input)) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value !== false) {
                $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
            }
        }
        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }
}
