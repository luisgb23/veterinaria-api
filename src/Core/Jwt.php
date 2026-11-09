<?php

namespace App\Core;

use RuntimeException;

/**
 * JWT firmado con HS256, implementado con funciones nativas de PHP.
 */
class Jwt
{
    /** Secreto de firma (JWT_SECRET en .env, mínimo 32 caracteres). */
    private static function secret(): string
    {
        $secret = Env::get('JWT_SECRET', '');

        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'JWT_SECRET no está configurado o es demasiado corto (mínimo 32 caracteres). '
                . 'Generá uno con: php -r "echo bin2hex(random_bytes(32));"'
            );
        }

        return $secret;
    }

    /**
     * Genera un token con los claims indicados, más "iat" y "exp".
     */
    public static function encode(array $claims, int $ttl): string
    {
        $now = time();
        $payload = $claims + ['iat' => $now, 'exp' => $now + $ttl];

        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(json_encode($payload));
        $signature = self::b64(hash_hmac('sha256', "$header.$body", self::secret(), true));

        return "$header.$body.$signature";
    }

    /**
     * Devuelve el payload si el token es válido (firma, algoritmo y vencimiento); si no, null.
     */
    public static function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $body64, $signature] = $parts;

        // Solo se acepta HS256: evita ataques con "alg": "none" u otros algoritmos.
        $header = json_decode(self::unb64($header64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = self::b64(hash_hmac('sha256', "$header64.$body64", self::secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::unb64($body64), true);
        if (!is_array($payload) || !isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] <= time()) {
            return null;
        }

        return $payload;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
