<?php

/*
| Paket SaaS (Blueprint §4.2). null = tanpa batas.
| Modul yang belum dibangun tetap didaftarkan agar paket bisa disiapkan sejak awal.
*/
return [
    'trial_days' => 14,

    'modules' => [
        'core' => 'Fondasi (cabang, pengguna, profil, tema, audit)',
        'menu' => 'Menu & harga',
        'inventory' => 'Resep, HPP & stok',
        'pos' => 'Kasir/POS & pembayaran',
        'order_ahead' => 'Order Ahead & QR meja',
        'kitchen' => 'Layar dapur (KDS)',
        'finance' => 'Closing kasir & keuangan',
        'hr' => 'Karyawan, jadwal & absensi',
        'payroll' => 'Payroll',
        'crm' => 'Pelanggan & loyalitas',
    ],

    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'max_branches' => 1,
            'max_users' => 5,
            'modules' => ['core', 'menu', 'pos', 'finance'],
        ],
        'growth' => [
            'label' => 'Growth',
            'max_branches' => 3,
            'max_users' => 25,
            'modules' => ['core', 'menu', 'inventory', 'pos', 'order_ahead', 'kitchen', 'finance', 'hr'],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'max_branches' => null,
            'max_users' => null,
            'modules' => ['core', 'menu', 'inventory', 'pos', 'order_ahead', 'kitchen', 'finance', 'hr', 'payroll', 'crm'],
        ],
    ],
];
