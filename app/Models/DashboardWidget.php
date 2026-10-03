<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Widget dashboard admin — baris TETAP (id = kunci pilihan di halaman Vue).
 */
class DashboardWidget extends Model
{
    protected $fillable = [
        'id',
        'label',
        'description',
        'enabled',
        'sort_order',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Definisi bawaan (id, label, deskripsi, enabled awal, urutan render).
     *
     * @return list<array{id: string, label: string, description: string, enabled: bool, sort_order: int}>
     */
    public static function defaults(): array
    {
        return [
            [
                'id' => 'status_ringkas',
                'label' => 'Kartu Status Ringkas',
                'description' => 'Jumlah perangkat, uptime, dan tiket terbuka.',
                'enabled' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 'trend_trafik',
                'label' => 'Tren Trafik 24 Jam',
                'description' => 'Grafik area gabungan RX/TX semua perangkat.',
                'enabled' => true,
                'sort_order' => 20,
            ],
            [
                'id' => 'cpu_rtt',
                'label' => 'Grafik CPU & Latensi 24 Jam',
                'description' => 'Rata-rata CPU (%) dan RTT ping (ms) per jam.',
                'enabled' => true,
                'sort_order' => 30,
            ],
            [
                'id' => 'donut_status',
                'label' => 'Donut Status Perangkat',
                'description' => 'Proporsi perangkat online / gangguan / baru.',
                'enabled' => false,
                'sort_order' => 40,
            ],
            [
                'id' => 'top_perangkat',
                'label' => 'Peringkat Trafik Perangkat',
                'description' => 'Perangkat dengan trafik RX+TX terbesar saat ini.',
                'enabled' => false,
                'sort_order' => 50,
            ],
            [
                'id' => 'top_interface',
                'label' => 'Top Interface Trafik',
                'description' => 'Interface dengan trafik terbesar lintas perangkat.',
                'enabled' => false,
                'sort_order' => 60,
            ],
            [
                'id' => 'status_perangkat',
                'label' => 'Daftar Status Perangkat',
                'description' => 'Status live, CPU, dan RTT tiap perangkat.',
                'enabled' => true,
                'sort_order' => 80,
            ],
            [
                'id' => 'tiket_terbuka',
                'label' => 'Tiket Terbaru',
                'description' => 'Enam laporan gangguan terakhir beserta statusnya.',
                'enabled' => true,
                'sort_order' => 90,
            ],
        ];
    }
}
