<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Cria três usuários de demonstração (senha "password") e um cenário realista
     * de depósitos, transferências e um estorno, para o avaliador testar de imediato.
     */
    public function run(WalletService $wallet): void
    {
        if (User::where('email', 'ana@example.com')->exists()) {
            return;
        }

        $ana = User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);
        $bruno = User::factory()->create(['name' => 'Bruno Lima', 'email' => 'bruno@example.com']);
        $carla = User::factory()->create(['name' => 'Carla Dias', 'email' => 'carla@example.com']);

        $anaWallet = $ana->ensureWallet();
        $brunoWallet = $bruno->ensureWallet();
        $carlaWallet = $carla->ensureWallet();

        // Valores em centavos.
        $wallet->deposit($anaWallet, 100000, 'Salário', $ana);
        $wallet->deposit($brunoWallet, 50000, 'Salário', $bruno);
        $wallet->deposit($carlaWallet, 30000, 'Salário', $carla);

        $wallet->transfer($anaWallet, $brunoWallet, 25000, 'Aluguel', $ana);
        $wallet->transfer($brunoWallet, $carlaWallet, 10000, 'Almoço', $bruno);

        $bonus = $wallet->deposit($carlaWallet, 20000, 'Bônus lançado por engano', $carla);
        $wallet->reverse($bonus, $carla, 'Estorno do bônus');
    }
}
