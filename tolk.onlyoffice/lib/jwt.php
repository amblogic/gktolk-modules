<?php
namespace Tolk\OnlyOffice;

/** Минимальная реализация JWT HS256 — без composer-зависимостей. */
final class Jwt
{
    public static function encode(array $payload, string $secret): string
    {
        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = self::b64(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $s = self::b64(hash_hmac('sha256', $h . '.' . $p, $secret, true));
        return $h . '.' . $p . '.' . $s;
    }

    public static function decode(string $jwt, string $secret): ?array
    {
        if ($secret === '') {
            return null;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $calc = self::b64(hash_hmac('sha256', $h . '.' . $p, $secret, true));
        if (!hash_equals($calc, $s)) {
            return null;
        }
        $data = json_decode(self::unb64($p), true);
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['exp']) && (int)$data['exp'] < time()) {
            return null;
        }
        return $data;
    }

    private static function b64(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function unb64(string $d): string
    {
        return (string)base64_decode(strtr($d, '-_', '+/') . str_repeat('=', (4 - strlen($d) % 4) % 4));
    }
}
