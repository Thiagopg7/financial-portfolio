<?php

use App\Support\Money;

it('converte reais em centavos', function (string $reais, int $expected) {
    expect(Money::toCents($reais))->toBe($expected);
})->with([
    'centavo mínimo' => ['0.01', 1],
    'dez centavos' => ['0.10', 10],
    'um real' => ['1.00', 100],
    'valor com centavos' => ['10.50', 1050],
    'borda de ponto flutuante' => ['10.07', 1007],
    'inteiro sem decimais' => ['100', 10000],
    'valor alto' => ['999999.99', 99999999],
]);
