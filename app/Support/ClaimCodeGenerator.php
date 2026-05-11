<?php

namespace App\Support;

use App\Models\ClaimCode;

/**
 * Generador de códigos de invitación de 8 chars. Excluye chars confusos
 * (0/O, 1/I/L) y reintenta hasta encontrar uno libre (UNIQUE constraint en
 * claim_codes.code es la garantía final).
 */
class ClaimCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    private const MAX_ATTEMPTS = 10;

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = self::randomString();

            if (! ClaimCode::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException(
            'No se pudo generar un código único después de '.self::MAX_ATTEMPTS.' intentos.'
        );
    }

    private static function randomString(): string
    {
        $alphabet = self::ALPHABET;
        $maxIdx = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= $alphabet[random_int(0, $maxIdx)];
        }

        return $out;
    }
}
