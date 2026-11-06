<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder utama. Dipanggil oleh php artisan migrate --seed atau db:seed.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seeder akun lalu seeder pengumuman.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PengumumanSeeder::class,
        ]);
    }
}
