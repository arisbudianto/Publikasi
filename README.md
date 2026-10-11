# Publikasi
Katalog jurnal pendidikan terbuka dengan kurasi sumber resmi.

## M01
Laravel 13, Blade, dan driver MySQL. Hosting cPanel menggunakan MariaDB dengan persetujuan pengguna.
Katalog publik menyediakan pencarian nama/ISSN dan detail bersumber. Admin mengelola jurnal, arsip, dan impor CSV dengan audit perubahan.

## Pengembangan
```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```
Konfigurasi database terpisah sebelum menjalankan migrasi dan seed. Tidak ada password admin default.
Panduan: [M01](docs/m01/README.md). Dokumen [M00](docs/m00/BLUEPRINT.md) menyimpan discovery awal; keputusan terbaru ada pada M01.
