<?php

namespace App\Support;

class Money
{
    /**
     * Converte um valor em reais (ex.: "10.50") para centavos.
     */
    public static function toCents(string $reais): int
    {
        // round evita o erro de ponto flutuante (ex.: 10.07 * 100 = 1006.9999...).
        return (int) round(((float) $reais) * 100);
    }
}
