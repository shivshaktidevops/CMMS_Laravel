<?php
namespace App\Services;

use RuntimeException;

class JwtService
{
    public function encode(array $claims): string
    {
        $now = time();
        $ttl = (int) env('JWT_TTL', 720) * 60;
        $payload = array_merge(['iat' => $now, 'exp' => $now + $ttl], $claims);
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = $this->b64(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = $this->b64(hash_hmac('sha256', "$h.$p", $this->secret(), true));
        return "$h.$p.$sig";
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Invalid token');
        [$h,$p,$s] = $parts;
        $expected = $this->b64(hash_hmac('sha256', "$h.$p", $this->secret(), true));
        if (!hash_equals($expected, $s)) throw new RuntimeException('Invalid token');
        $payload = json_decode($this->unb64($p), true);
        if (!is_array($payload)) throw new RuntimeException('Invalid token');
        if (isset($payload['exp']) && time() >= (int)$payload['exp']) throw new RuntimeException('Token expired');
        return $payload;
    }

    private function secret(): string
    {
        return (string) env('JWT_SECRET', 'cmms_secret_change_in_production');
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function unb64(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) throw new RuntimeException('Invalid token');
        return $decoded;
    }
}
