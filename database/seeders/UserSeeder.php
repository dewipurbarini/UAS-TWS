<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Mengisi dua akun uji.
 * Password di-hash dengan Hash::make (bcrypt). Jangan menyimpan teks asli di database.
 */
class UserSeeder extends Seeder
{
    /**
     * Menulis akun admin dan user.
     * Kembalian: tidak ada.
     */
    public function run(): void
    {
        // Akun admin. Password uji: admin123 (disebutkan juga di README).
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'nama' => 'Dewi Purbarini',
                'role' => 'admin',
            ]
        );

        // Akun mahasiswa biasa. Password uji: user123. Tidak boleh mengubah data.
        User::query()->updateOrCreate(
            ['username' => 'user'],
            [
                'password' => Hash::make('user123'),
                'nama' => 'Mahasiswa Uji',
                'role' => 'user',
            ]
        );
    }
}
