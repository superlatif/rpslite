<?php

return [
    // Ukuran kertas struk default: '58' atau '80'
    'paper_size' => '80',

    // Lebar karakter per baris sesuai ukuran kertas
    'paper_widths' => [
        '58' => 32,
        '80' => 48,
    ],

    // Lebar kolom [nama, qty, jumlah] sesuai ukuran kertas
    'column_widths' => [
        '58' => [17, 4, 11],
        '80' => [24, 6, 18],
    ],

    // Nama printer CUPS default
    'printer' => 'ThermalRaw',
];
