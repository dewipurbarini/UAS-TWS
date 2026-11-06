<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model akun API.
 * Fungsi: memetakan tabel users. Password tidak pernah ikut saat model diubah jadi array.
 */
class User extends Authenticatable
{
    // Agar factory bawaan tetap bisa dipakai. Seeder tugas tidak bergantung pada factory.
    use HasFactory;

    /**
     * Kolom yang boleh diisi massal dari seeder.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'nama',
        'role',
    ];

    /**
     * Password disembunyikan bila model ikut ke JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Tabel ini hanya punya created_at, tanpa updated_at.
     */
    public const UPDATED_AT = null;
}
