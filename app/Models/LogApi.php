<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model baris log request API.
 * Tidak memakai timestamps Laravel karena waktu sudah ada di kolom waktu.
 */
class LogApi extends Model
{
    /**
     * Nama tabel.
     */
    protected $table = 'log_api';

    /**
     * created_at dan updated_at tidak ada di tabel ini.
     */
    public $timestamps = false;

    /**
     * Kolom yang diisi middleware LogApi.
     *
     * @var list<string>
     */
    protected $fillable = [
        'waktu',
        'method',
        'path',
        'status',
        'ip',
        'username',
        'pesan',
    ];

    /**
     * waktu dibaca sebagai objek tanggal. status sebagai integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'status' => 'integer',
        ];
    }
}
