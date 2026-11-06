<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider aplikasi bawaan Laravel.
 * Tugas ini tidak menaruh binding tambahan di sini. JWT dibaca lewat config/jwt.php.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Mendaftarkan service. Tidak ada yang didaftarkan khusus.
     */
    public function register(): void
    {
        // Dikosongkan dengan sengaja.
    }

    /**
     * Berjalan setelah semua service siap. Tidak ada pengaturan tambahan.
     */
    public function boot(): void
    {
        // Dikosongkan dengan sengaja.
    }
}
