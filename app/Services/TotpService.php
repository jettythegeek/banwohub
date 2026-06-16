<?php

namespace App\Services;

use Illuminate\Support\Str;

class TotpService
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    public function generateSecret(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountName);
        $issuerParam = rawurlencode($issuer);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&period=".self::PERIOD.'&digits='.self::DIGITS;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $timestamp = time();
        for ($offset = -$window; $offset <= $window; $offset++) {
            $counter = intdiv($timestamp, self::PERIOD) + $offset;
            if (hash_equals($this->codeForCounter($secret, $counter), $normalized)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secret): string
    {
        return $this->codeForCounter($secret, intdiv(time(), self::PERIOD));
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );
        $otp = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/\s+/', '', $secret) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    public function challengeToken(): string
    {
        return Str::random(64);
    }
}
