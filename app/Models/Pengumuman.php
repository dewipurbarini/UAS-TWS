<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model pengumuman kampus.
 * Nama tabel diset manual karena bentuk jamaknya bukan "pengumumans".
 */
class Pengumuman extends Model
{
    /**
     * Nama tabel di MySQL.
     */
    protected $table = 'pengumuman';

    /**
     * Hanya created_at yang ada. updated_at tidak dipakai.
     */
    public const UPDATED_AT = null;

    /**
     * Kolom yang boleh diisi dari request yang sudah divalidasi.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kode_info',
        'judul',
        'kategori',
        'tanggal',
        'penulis',
        'jumlah_dibaca',
    ];

    /**
     * Mengubah tipe saat dibaca.
     * tanggal menjadi tanggal Y-m-d. jumlah_dibaca menjadi integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'jumlah_dibaca' => 'integer',
        ];
    }
}
