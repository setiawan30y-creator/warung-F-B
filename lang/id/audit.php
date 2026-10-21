<?php

return [
    'events' => [
        'auth.login' => 'masuk ke aplikasi',
        'auth.logout' => 'keluar dari aplikasi',
        'auth.failed' => 'gagal masuk',
        'tenant.created' => 'membuat tenant',
        'tenant.updated' => 'mengubah status tenant',
        'tenant_subscription.created' => 'membuat langganan',
        'tenant_subscription.updated' => 'mengubah langganan',
        'branch.created' => 'menambah cabang',
        'branch.updated' => 'mengubah cabang',
        'branch.deleted' => 'menghapus cabang',
        'user.created' => 'menambah pengguna',
        'user.updated' => 'mengubah pengguna',
        'user.deleted' => 'menghapus pengguna',
        'user.access_changed' => 'mengubah peran/akses cabang pengguna',
        'role.created' => 'membuat peran',
        'role.updated' => 'mengubah peran',
        'role.deleted' => 'menghapus peran',
        'role.permissions_changed' => 'mengubah izin peran',
        'company_profile.created' => 'membuat profil perusahaan',
        'company_profile.updated' => 'mengubah profil perusahaan',
        'theme_setting.created' => 'membuat tema',
        'theme_setting.updated' => 'mengubah tema',
    ],
];
