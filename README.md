# ALMARA Restaurant & Beverage OS

Platform SaaS multi-tenant untuk usaha makanan dan minuman, dari warung dengan satu kasir hingga grup restoran multi-cabang. Repositori ini dibangun bertahap mengikuti *Blueprint Induk v1.1* (21 Oktober 2026).

Dikembangkan oleh **@2026-Alifiandra**.

## Status: Fase 1 dan Fase 2 selesai

### Fase 1 — Fondasi SaaS

| Bagian blueprint | Yang sudah tersedia |
|---|---|
| §4.1 Isolasi data | Konteks tenant per request/job, global scope `tenant_id` otomatis, penolakan tulis lintas tenant, validasi relasi per tenant |
| §4.2 Paket SaaS | Paket Starter/Growth/Enterprise (`config/saas.php`), batas cabang & pengguna, masa uji coba, status langganan tanpa menghapus data |
| §4.3 Multi-cabang | Cabang aktif per sesi, akses cabang per pengguna diperiksa di server, jam operasional, koordinat, zona waktu WIB/WITA/WIT |
| §5.1 Profil perusahaan | Identitas, kontak, NIB/NPWP, teks nota, unggah logo (pratinjau, validasi format/ukuran/dimensi, ganti/hapus), disimpan per folder tenant |
| §5.2 Tema global | Mode terang/gelap/sistem, 5 preset, warna kustom HEX untuk tiap token, pratinjau langsung, cek kontras WCAG, pilihan huruf & sudut, preferensi mode per pengguna |
| §5.3 Widget | Cuaca (Open-Meteo, data lama ditandai bila gagal diperbarui) dan lokasi cabang aktif, bisa dimatikan |
| §17 (akun) / §23 | Pengguna, peran bawaan + peran kustom, matriks izin, pencegahan eskalasi hak, log audit append-only dengan nilai sebelum/sesudah |
| §3.3 PWA | Manifest dinamis per tenant, service worker yang hanya menyimpan aset statis, banner saat koneksi terputus |
| Admin platform | Membuat tenant (langganan, profil, tema, peran, cabang pertama, akun pemilik), mengubah paket/status, menonaktifkan tenant |

### Fase 2 — Menu, resep, HPP, dan stok

| Bagian blueprint | Yang sudah tersedia |
|---|---|
| §6 Menu | Kategori (urutan bisa diatur), foto, harga dasar + harga per cabang, dijual/tidak per cabang, tanda habis, riwayat harga |
| §12.1.1 Sub-resep | Racikan (saus, bumbu tabur, sambal, dll.) sebagai bahan tersendiri dengan stok dan HPP sendiri; dipakai di banyak menu; HPP menu mengambil biaya racikan sehingga tidak dihitung ganda; pencegahan resep melingkar |
| §12.1 Versi resep | Setiap perubahan membuat versi baru; versi lama tidak bisa diubah, jadi HPP historis tetap |
| §12.2 Satuan | 28 satuan standar (g, kg, ons, ml, l, pcs, butir, lusin, kodi, rim, karung, tray, karton, dll.) + isi kemasan per bahan |
| §12.3 HPP | Rata-rata tertimbang per cabang, fallback ke HPP resep/harga beli terakhir, margin, simulasi harga jual |
| §11.3 Produksi | Batch racikan: konsumsi aktual mengurangi bahan baku, hasil aktual menambah stok racikan dengan HPP aktual, selisih di atas 2% wajib beralasan |
| §13.1 Stok | Ledger `stock_movements` append-only sebagai sumber kebenaran, saldo per cabang, kartu stok, saldo awal, penyesuaian berizin, stok minimum, rekonsiliasi (`php artisan almara:stock-reconcile`) |
| §13.2 Susut | Alasan wajib, nilai kerugian, persetujuan oleh pengguna lain bila di atas batas (`WASTE_APPROVAL_THRESHOLD`), laporan per alasan dan bahan |
| §13.1 Opname | Hitung fisik per kategori, selisih dihitung terhadap stok saat posting; gudang menghitung, manajer memposting |
| §13.3 Pembelian | Supplier, PO dengan nomor aman-konkuren, penerimaan sebagian, harga acuan bahan otomatis diperbarui |
| §4.2 Paket | Modul stok hanya untuk paket Growth/Enterprise; Starter hanya mendapat menu |

Layanan `SaleConsumption` sudah siap dipanggil POS di Fase 3: menjual menu mengurangi stok racikan (bukan bahan mentahnya) dan mengembalikan HPP snapshot.

Modul berikutnya mengikuti roadmap §29: Fase 3 POS & pembayaran; Fase 4 KDS; Fase 5 closing & keuangan; Fase 6 karyawan, absensi & payroll.

### Memperbarui instalasi yang sudah ada

```powershell
git pull
composer install
php artisan migrate
php artisan almara:sync-permissions
php artisan view:clear
```

`almara:sync-permissions` menambahkan izin baru (produksi, lihat stok, setujui susut) ke peran bawaan tanpa mengubah izin yang sudah Anda atur.

## Teknologi

Laravel 13 · PHP 8.3+ (target produksi 8.4) · Blade + Livewire 4 + Alpine.js · MySQL 8 (produksi) / SQLite (lokal & tes) · Redis + Queue + Scheduler · Nginx · Ubuntu 24.04.

Tidak ada langkah build front-end: CSS ada di `public/css/app.css` dan warnanya dikendalikan variabel dari Pengaturan Tema.

## Menjalankan di Windows (PowerShell)

Kebutuhan: PHP 8.3 atau lebih baru (dengan ekstensi `pdo_sqlite`, `mbstring`, `gd`, `intl`, `fileinfo`), Composer, Git.

```powershell
git clone https://github.com/setiawan30y-creator/warung-F-B.git
Set-Location warung-F-B

composer install
Copy-Item .env.example .env
php artisan key:generate

New-Item -ItemType File database\database.sqlite -Force | Out-Null
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # data contoh usaha kentang crispy, jangan di produksi
php artisan storage:link

php artisan serve
```

Buka `http://127.0.0.1:8000` lalu masuk dengan salah satu akun contoh (kata sandi `password123`):

| Email | Peran | Yang bisa dicoba |
|---|---|---|
| `pemilik@contoh.test` | Pemilik, semua cabang | Semua pengaturan |
| `manajer@contoh.test` | Manajer, semua cabang | Menyetujui susut, memposting opname |
| `gudang@contoh.test` | Gudang, Cabang Pusat | Mencatat susut dan menghitung opname, tanpa bisa menyetujui sendiri |
| `kasir@contoh.test` | Kasir, hanya Cabang Timur | Akses terbatas |
| `kedai@contoh.test` | Pemilik tenant lain (paket Starter) | Bukti isolasi data antar tenant |
| `admin@platform.test` | Admin platform | Kelola tenant & langganan |

Menjalankan tes:

```powershell
php artisan test
```

## Produksi (Ubuntu 24.04)

1. Salin `.env.example` ke `.env`, isi `APP_URL`, set `APP_ENV=production`, `APP_DEBUG=false`, koneksi MySQL, lalu `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`.
2. `composer install --no-dev --optimize-autoloader`, `php artisan key:generate`, `php artisan migrate --force`, `php artisan db:seed --force`, `php artisan storage:link`.
3. Buat admin platform: `php artisan almara:platform-admin --email=admin@domain-anda.id`.
4. `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
5. Contoh konfigurasi ada di folder `deploy/`: Nginx + HTTPS, Supervisor untuk antrean, dan cron untuk Scheduler.

Setiap kali katalog izin di kode berubah, jalankan `php artisan almara:sync-permissions`.

## Struktur

```
app/
├── Domain/
│   ├── Tenancy/        TenantContext, TenantScope, trait BelongsToTenant
│   ├── Identity/       PermissionCatalog (izin & peran bawaan)
│   ├── Audit/          AuditLogger, trait Auditable
│   ├── SaaS/           TenantProvisioner, PlanLimits
│   ├── Inventory/      StockService (ledger), RecipeCostService (HPP), RecipeService, ProductionService,
│   │                   PurchasingService, WasteService, StockCountService, SaleConsumption, UnitConverter
│   ├── Theme/          ThemePalette (preset, token, kontras)
│   └── Widgets/        WeatherService
├── Http/Middleware/    SetTenantContext, EnsureUserIsActive, EnsurePlatformAdmin
├── Livewire/           Halaman (Dashboard, Ops/*, Settings/*, Platform/*)
└── Models/
tests/Feature/          Isolasi tenant, kontrol akses, audit, langganan, stok & HPP, akses halaman operasional
```

### Aturan untuk modul berikutnya

- Setiap model data tenant memakai `BelongsToTenant` (dan `Auditable` bila perubahannya perlu dilacak). Kolom `tenant_id` wajib, `branch_id` untuk data per cabang.
- Komponen Livewire halaman tenant memakai trait `TenantPage` dan memeriksa izin di `mount()` dan di setiap aksi (`$this->authorize(...)`).
- Izin baru didaftarkan di `PermissionCatalog`, lalu jalankan `almara:sync-permissions`.
- Job antrean membungkus kerjanya dengan `app(TenantContext::class)->run($tenant, fn () => ...)`.
- Semua perubahan stok lewat `StockService::move()`; jangan pernah mengubah `stock_balances` langsung.
- Tambahkan tes akses silang tenant untuk setiap modul baru (kriteria penerimaan §30 no. 1–2).

## Catatan

- `composer.lock` disusun dari rilis resmi tiap paket di GitHub (Laravel 13.38, Livewire 4.4.8, dll.) dan sudah lulus seluruh tes.
- Widget cuaca memakai layanan publik Open-Meteo. Bila server tidak bisa mengaksesnya, widget menampilkan data terakhir atau pesan gagal tanpa mengganggu halaman.
- Fon Plus Jakarta Sans dimuat dari Google Fonts. Bila tidak tersedia, aplikasi memakai fon sistem.
