<?php

namespace App\Domain\Identity;

/**
 * Katalog izin tindakan. Izin modul fase berikutnya sudah didaftarkan agar peran
 * dapat disiapkan sejak awal; halamannya menyusul sesuai roadmap.
 */
class PermissionCatalog
{
    public const GROUPS = [
        'Pengaturan' => [
            'company.manage' => 'Kelola profil perusahaan & logo',
            'theme.manage' => 'Kelola tema tampilan',
            'branches.view' => 'Lihat cabang',
            'branches.manage' => 'Kelola cabang',
        ],
        'Pengguna & Akses' => [
            'users.view' => 'Lihat pengguna',
            'users.manage' => 'Kelola pengguna',
            'roles.manage' => 'Kelola peran & izin',
            'audit.view' => 'Lihat log audit',
        ],
        'Menu & Stok (Fase 2)' => [
            'menu.manage' => 'Kelola menu & harga',
            'recipes.manage' => 'Kelola resep & HPP',
            'inventory.manage' => 'Kelola stok',
            'purchasing.manage' => 'Kelola pembelian',
        ],
        'Penjualan (Fase 3–4)' => [
            'pos.use' => 'Gunakan kasir/POS',
            'pos.discount' => 'Beri diskon',
            'pos.void' => 'Batalkan transaksi',
            'kitchen.use' => 'Gunakan layar dapur',
        ],
        'Keuangan (Fase 5)' => [
            'closing.perform' => 'Lakukan closing kasir',
            'closing.approve' => 'Setujui/koreksi closing',
            'finance.view' => 'Lihat laporan keuangan',
        ],
        'Karyawan (Fase 6)' => [
            'employees.manage' => 'Kelola karyawan & jadwal',
            'attendance.approve' => 'Setujui absensi & lembur',
            'payroll.manage' => 'Kelola payroll',
        ],
    ];

    /** Peran bawaan per tenant. Peran "pemilik" selalu memiliki semua izin. */
    public const DEFAULT_ROLES = [
        'pemilik' => ['name' => 'Pemilik', 'description' => 'Akses penuh ke semua cabang dan pengaturan.', 'permissions' => '*'],
        'manajer' => ['name' => 'Manajer', 'description' => 'Mengelola operasional cabang.', 'permissions' => [
            'branches.view', 'users.view', 'audit.view', 'menu.manage', 'recipes.manage', 'inventory.manage',
            'purchasing.manage', 'pos.use', 'pos.discount', 'pos.void', 'kitchen.use', 'closing.perform',
            'closing.approve', 'finance.view', 'employees.manage', 'attendance.approve',
        ]],
        'kasir' => ['name' => 'Kasir', 'description' => 'Transaksi penjualan dan closing kasir.', 'permissions' => [
            'branches.view', 'pos.use', 'closing.perform',
        ]],
        'dapur' => ['name' => 'Dapur', 'description' => 'Layar dapur dan produksi.', 'permissions' => [
            'branches.view', 'kitchen.use',
        ]],
        'gudang' => ['name' => 'Gudang', 'description' => 'Stok dan pembelian.', 'permissions' => [
            'branches.view', 'inventory.manage', 'purchasing.manage',
        ]],
    ];

    public static function all(): array
    {
        $out = [];
        foreach (self::GROUPS as $group => $items) {
            foreach ($items as $key => $label) {
                $out[$key] = ['group' => $group, 'label' => $label];
            }
        }

        return $out;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
