# M19: agen pemantauan halaman jurnal

M19 membaca URL HTTPS pada domain yang sama persis dengan situs resmi jurnal terkurasi. Admin dapat mendaftarkan sumber satu per satu (awal dalam keadaan jeda), atau mendaftarkan halaman cakupan jurnal terbit yang sudah tercatat di katalog dengan perintah `m19:seed-scope`. `M19_ENABLED=false` adalah pengaman global. Jadwal Laravel berjalan setiap hari pukul 03.00 UTC (10.00 WIB) lewat cron `schedule:run` yang sudah ada. `M19_DAILY_LIMIT=20` membatasi jumlah jurnal berbeda per hari; nilai hanya dapat diatur antara 10 dan 20. Jumlah aktual bisa lebih rendah bila sumber aktif yang jatuh tempo kurang dari 10. Setiap sumber dijadwalkan lagi paling cepat 24 jam setelah pemeriksaan, termasuk kegagalan. Pemeriksaan manual tetap tunduk pada kuota harian.

## Alur

1. Admin membuka `/admin/agen-jurnal`, mendaftarkan halaman resmi untuk cakupan, biaya, waktu review, atau angka penerimaan/penolakan, dan mengaktifkan sumber. Alternatif untuk halaman cakupan yang sudah dikurasi: `php artisan m19:seed-scope --limit=200` menampilkan jumlah layak tanpa perubahan, lalu `php artisan m19:seed-scope --limit=200 --apply` mendaftarkan dan mengaktifkannya. Perintah memeriksa URL HTTPS dan kesamaan domain, mengabaikan sumber yang sudah ada, dan tidak melakukan permintaan jaringan.
2. `php artisan m19:tick` memeriksa robots.txt, mengambil hanya URL terdaftar tanpa mengikuti pengalihan, lalu menyimpan status dan hash halaman.
3. Ekstraktor berbasis aturan mengambil maksimal tiga kutipan singkat per halaman yang berubah. Ia tidak mengarang angka, tidak memakai API AI, dan tidak memasukkan hasil ke katalog. Halaman yang tidak memuat klaim eksplisit tetap menghasilkan nol kandidat.
4. Admin membuka sumber asli, meninjau kutipan, dan mencatat keputusan. Pembaruan katalog tetap dilakukan secara manual di alur kurasi M06/M10 dengan audit.

Kandidat tidak berarti klaim sudah terverifikasi. Angka penerimaan adalah klaim penerbit, bukan peluang naskah pengguna. Status akreditasi tidak diambil dari halaman jurnal.

## Operasi produksi

- Migrasi additive `2026_10_13_000005_create_m19_agent_tables.php` menambah tiga tabel kosong. Lakukan backup database dahulu. Tidak ada perubahan terhadap data jurnal lama.
- Deploy kode, `php artisan migrate --force`, lalu cache config, route, dan view yang relevan. Verifikasi `/admin/agen-jurnal` hanya dapat diakses admin dan `php artisan m19:tick` menghasilkan `disabled` sebelum aktivasi.
- Periksa daftar URL yang hendak diaktifkan dan jalankan `m19:seed-scope` dalam mode pratinjau dahulu. Setelah mengaktifkan sumber dan uji sumber pertama, set `M19_ENABLED=true` pada `.env` produksi dan jalankan `php artisan config:cache`. Periksa `php artisan schedule:list` dan cron `schedule:run` setiap menit; jangan menambah cron kedua.
- Periksa `m19_runs` berkala: `error_code` berisi kode ringkas, bukan isi respons. Nonaktifkan sumber lewat UI atau set `M19_ENABLED=false` untuk berhenti. Halaman Scopus yang hanya memiliki nama/ISSN tanpa URL resmi tidak otomatis menjadi sumber crawl. Snapshot Scopus/SINTA tetap membutuhkan data indeks resmi tersendiri; halaman jurnal tidak dapat membuktikan quartile atau akreditasi.
- Rollback kode dengan release sebelumnya; tabel dan bukti yang sudah terkumpul boleh tetap ada. Jangan rollback migrasi pada database produksi jika data M19 sudah terisi.

## Ekstraksi AI (opsional)

Migrasi `2026_10_13_000006_add_m19_ai_status.php` menambah penanda hasil AI pada setiap pemeriksaan. Setelah migrasi, `M19_AI_ENABLED=true` mengaktifkan AI untuk halaman yang berubah jika kunci penyedia M15 tersedia. Model dan harga mengikuti konfigurasi M15; setiap panggilan harus melewati anggaran bersama `M15Budget` (batas keseluruhan tetap `M15_MONTHLY_BUDGET_USD`). `M19_AI_MONTHLY_LIMIT=10` membatasi pemeriksaan AI per bulan, dan batas ini dapat diturunkan dalam `.env`. Jangan aktifkan AI jika anggaran M15 dibutuhkan sepenuhnya untuk analisis posisi riset.

AI menerima paling banyak 12.000 karakter teks paragraf halaman resmi yang sudah diambil lewat pemfetch aman M19. Respons JSON yang tidak valid dicoba ulang sekali dengan instruksi format yang lebih tegas. Kedua panggilan dihitung dalam anggaran M15; kegagalan HTTP tidak dicoba ulang. Batas keluaran bawaan 2.000 token dapat disesuaikan dengan `M19_AI_MAX_COMPLETION_TOKENS`. Hanya kutipan yang benar-benar terdapat dalam paragraf yang dikirim dan memenuhi syarat bidang yang disimpan, maksimal tiga kandidat per pemeriksaan bersama hasil aturan biasa. Jawaban kosong atau kegagalan AI tidak menghalangi pemeriksaan berdasarkan aturan. Semua kandidat tetap berstatus menunggu dan tidak mengubah katalog. `ai_status` pada `m19_runs` berisi `completed`, `failed`, atau `NULL` jika AI tidak dicoba. Matikan AI seketika dengan `M19_AI_ENABLED=false` lalu `php artisan config:cache`; tidak diperlukan cron baru.

## Batasan tahap pertama

HTML saja, tanpa PDF/JavaScript/OAI. Pemeriksaan `robots.txt` konservatif dan permintaan jaringan memakai cURL dengan IP publik yang dipastikan saat koneksi, HTTPS dan sertifikat TLS bawaan, tanpa pengalihan, maksimal 256 KiB/halaman, timeout 15 detik. Tidak ada penyimpanan HTML penuh; hash dan kutipan singkat tersimpan sebagai bukti yang perlu diverifikasi kembali oleh admin. Parser dan AI hanya menghasilkan kandidat yang jelas; beberapa pernyataan tidak akan ditemukan. Pelacakan ETag, snapshot halaman, dan adaptor OJS masih di luar cakupan.
