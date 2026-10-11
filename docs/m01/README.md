# M01 Katalog dan admin
Keputusan pengguna: pendidikan; kurasi sumber resmi; katalog terbuka; Laravel 13; MariaDB cPanel melalui driver mysql disetujui pada 2026-10-11.
MVP_SCOPE=READY untuk M01. Arsitektur Laravel 13/Blade/MySQL driver. Hosting dan HTTP harus diuji sebelum rilis.
Fitur: pencarian nama/ISSN, pagination, detail bersumber, login admin, CRUD/arsip, audit perubahan, CSV preview dan commit transaksional.
Belum mencakup matching AI, akun publik, pembayaran, atau verifikasi independen seluruh indeksasi.
Data pilot merupakan parafrasa scope situs resmi; tidak menyalin artikel atau database pihak lain. Klaim indeksasi penerbit ditandai publisher_claim.
Setup: composer install; konfigurasi .env server; php artisan key:generate; php artisan migrate; php artisan db:seed --class=PilotJournalSeeder.
Admin: php artisan publikasi:admin email-admin. Password dimasukkan tersembunyi dan tidak memiliki default.
CSV header: name,publisher,issn,official_url,subject,scope_summary,scope_source_url,checked_at,status.
Impor hanya insert, maksimal 500 baris/1MB, preview 15 menit; konflik tidak overwrite dan commit rollback jika satu baris invalid.
Audit menyimpan before/after katalog. Riwayat observasi tidak dihapus oleh edit scope.
Testing menggunakan DB terisolasi. Jangan menjalankan RefreshDatabase pada database produksi.
