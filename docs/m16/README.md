# M16 Pantauan topik dan versi ide

Pantauan bersifat opt in untuk akun login. Pengguna menyimpan hingga tiga topik, masing-masing hingga lima versi. Judul dan ringkasan tersimpan memakai cast terenkripsi Laravel; publikasi yang ditemukan disimpan sebagai metadata tanpa abstrak. Penghapusan topik menghapus versi dan temuannya melalui foreign key. Tidak ada email.

Pemeriksaan OpenAlex berjalan lewat `m16:tick` setiap 15 menit ketika cPanel menjalankan `artisan schedule:run` setiap menit. Setiap topik diperiksa paling sering sekali per 24 jam. Maksimal lima topik per tick dan 60 permintaan OpenAlex per bulan untuk M16, termasuk pembuatan topik dan versi. Permintaan diklaim sebelum HTTP; kegagalan juga dihitung. Biaya yang dilaporkan OpenAlex dicatat oleh ledger M15. Tidak ada panggilan Dinoiki untuk M16.

Hasil dipilih dengan irisan kata judul/ringkasan dan metadata publikasi. Ambang 0,25 dan minimal dua kata sama merupakan filter awal, bukan ukuran kebaruan atau kemiripan semantik. Temuan berarti kandidat belum ada di snapshot pemeriksaan versi aktif sebelumnya. Publikasi tersebut bisa terbit lebih lama, baru terindeks, atau masuk karena peringkat pencarian berubah. Perbandingan versi menampilkan kandidat bertambah, hilang, dan muncul pada keduanya; ia tidak menyimpulkan perubahan pada empat dimensi M15.

## Urutan rilis cPanel

1. Ambil source `main` pada commit merge M16 yang persis, pastikan checkout bersih. Simpan backup aplikasi untuk file yang berubah dan dump database baru; verifikasi gzip dan checksum. Jangan ulang migrasi M15.
2. Arsipkan migrasi M16 dari commit tersebut ke runtime. Jalankan `php artisan migrate --force --path=database/migrations/2026_10_13_000003_create_m16_monitoring_tables.php` dan verifikasi status serta empat tabel. Ini migrasi additive.
3. Salin file runtime M16 dari commit yang sama. Jalankan lint PHP, `config:cache`, `route:cache`, `view:cache`, lalu cek halaman akun dan `/akun/pantauan`. Default `M16_ENABLED=false`, jadi modul belum menerima topik baru.
4. Periksa PHP CLI absolut dan daftar cron cPanel. Tambahkan satu jadwal per menit yang menjalankan `PHP_ABSOLUT /home/speakver/publikasi.speakverse.id/artisan schedule:run` dengan working directory aplikasi. Jangan membuat jadwal kedua bila scheduler aplikasi sudah berjalan. Zona waktu aplikasi UTC; tick jatuh pada menit 00, 15, 30, 45.
5. Bukti cron harus berasal dari dua eksekusi otomatis pada slot berbeda dengan jarak sedikitnya 10 menit. Lihat `m16_runs` (`source=cron`, `finished_at`, `requests`, `failures`) dan bandingkan dengan log/timestamp cron cPanel. Eksekusi manual `m16:tick` tidak membuktikan cron.
6. Sesudah bukti tersebut, set `M16_ENABLED=true` di `.env` runtime dan jalankan `php artisan config:cache`. Buka akun login, buat topik sintetik dengan persetujuan, lihat snapshot versi 1, buat versi 2, cek perbandingan, tandai temuan bila ada, hapus topik. Jangan pakai abstrak rahasia.

Jika cron berhenti lebih dari 36 jam, form pantauan terkunci otomatis. Tick baru akan memulihkan readiness setelah dua run berjarak sesuai syarat. Saat rollback kode, nonaktifkan `M16_ENABLED`, lepaskan schedule cron hanya jika scheduler itu khusus M16; jangan rollback tabel berisi topik pengguna tanpa backup dan rencana pemulihan.
