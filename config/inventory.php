<?php

return [
    // Susut dengan nilai di atas batas ini perlu persetujuan pengguna berizin "inventory.approve" (Blueprint §13.2).
    'waste_approval_threshold' => (float) env('WASTE_APPROVAL_THRESHOLD', 50000),

    // Selisih hasil produksi di atas persentase ini wajib diberi alasan.
    'production_variance_tolerance' => 0.02,

    // Penjualan tidak diblokir oleh stok sistem; selisih ditangkap lewat stok opname.
    'allow_negative_on_sale' => true,
];
