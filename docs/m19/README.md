# M19: agen pemantauan halaman jurnal

M19 membaca URL HTTPS yang didaftarkan admin pada domain yang sama persis dengan situs resmi jurnal terkurasi. Sumber baru dijeda. Admin mengaktifkannya satu per satu. `M19_ENABLED=false` adalah pengaman global. Satu jadwal Laravel harian pukul 03.00 UTC (10.00 WIB) memakai cron `schedule:run` yang sudah ada, tanpa cron tambahan. Maksimal lima sumber jatuh tempo diproses per eksekusi; sumber yang selesai maupun gagal dijadwalkan ulang seminggu kemudian. Karena pemeriksaan jatuh tempo dilakukan harian, satu sumber diperiksa sekitar sekali seminggu.

## Alur

1. Admin membuka `/admin/agen-jurnal`, mendaftarkan halaman resmi untuk cakupan, biaya, waktu review, atau angka penerimaan/penolakan, dan mengaktifkan sumber.
2. `php artisan m19:tick` memeriksa robots.txt, mengambil hanya URL terdaftar tanpa mengikuti pengalihan, lalu menyimpan status dan hash halaman.
3. Ekstraktor berbasis aturan mengambil maksimal tiga kutipan singkat per halaman yang berubah. Ia tidak mengarang angka, tidak memakai API AI, dan tidak memasukkan hasil ke katalog. Halaman yang tidak memuat klaim eksplisit tetap menghasilkan nol kandidat.
4. Admin membuka sumber asli, meninjau kutipan, dan mencatat keputusan. Pembaruan katalog tetap dilakukan secara manual di alur kurasi M06/M10 dengan audit.

Kandidat tidak berarti klaim sudah terverifikasi. Angka penerimaan adalah klaim penerbit, bukan peluang naskah pengguna. Status akreditasi tidak diambil dari halaman jurnal.

## Operasi produksi

- Migrasi additive `2026_10_13_000005_create_m19_agent_tables.php` menambah tiga tabel kosong. Lakukan backup database dahulu. Tidak ada perubahan terhadap data jurnal lama.
- Deploy kode, `php artisan migrate --force`, lalu cache config, route, dan view yang relevan. Verifikasi `/admin/agen-jurnal` hanya dapat diakses admin dan `php artisan m19:tick` menghasilkan `disabled` sebelum aktivasi.
- Setelah uji sumber pertama dan memeriksa log, set `M19_ENABLED=true` pada `.env` produksi dan jalankan `php artisan config:cache`.
- Periksa `m19_runs` berkala: `error_code` berisi kode ringkas, bukan isi respons. Nonaktifkan sumber lewat UI atau set `M19_ENABLED=false` untuk berhenti. Jangan tambah cron kedua.
- Rollback kode dengan release sebelumnya; tabel dan bukti yang sudah terkumpul boleh tetap ada. Jangan rollback migrasi pada database produksi jika data M19 sudah terisi.

## Batasan tahap pertama

HTML saja, tanpa PDF/JavaScript/OAI. Pemeriksaan `robots.txt` konservatif dan permintaan jaringan memakai cURL dengan IP publik yang dipastikan saat koneksi, HTTPS dan sertifikat TLS bawaan, tanpa pengalihan, maksimal 256 KiB/halaman, timeout 15 detik. Tidak ada penyimpanan HTML penuh; hash dan kutipan singkat tersimpan sebagai bukti yang perlu diverifikasi kembali oleh admin. Parser berbasis aturan sengaja hanya menghasilkan kandidat yang jelas; beberapa pernyataan tidak akan ditemukan. Pelacakan ETag, snapshot halaman, adaptor OJS, dan ekstraksi AI berbatas biaya merupakan tahap berikutnya setelah mutu pilot diukur.
