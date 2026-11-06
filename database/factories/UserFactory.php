<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Factory bawaan Laravel, disesuaikan dengan kolom users tugas ini.
 * Seeder tugas tidak memakai factory. File ini tetap ada agar perintah make/factory tidak rusak.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Password yang dipakai bila factory dipanggil tanpa password khusus.
     */
    protected static ?string $password;

    /**
     * Bentuk satu user palsu.
     * Kembalian: array kolom yang sesuai tabel users.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Username unik agar tidak bentrok dengan unique index.
            'username' => fake()->unique()->userName(),
            // Hash, bukan teks biasa. Factory ini tidak dipakai untuk akun uji admin/user.
            'password' => static::$password ??= Hash::make('password'),
            'nama' => fake()->name(),
            'role' => 'user',
        ];
    }
}
