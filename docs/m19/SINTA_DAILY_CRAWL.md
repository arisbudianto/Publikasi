# Pemantauan harian sumber SINTA

Sumber data adalah kolom `official_url` pada `journal_sources` yang telah diimpor. URL ini berasal dari snapshot SINTA dan tidak otomatis dianggap bukti mutakhir atau domain terkurasi. Pemfetch M19 memeriksa HTTPS, IP publik pada saat koneksi, TLS, robots.txt, tipe HTML, ukuran maksimal 256 KiB, dan tidak mengikuti redirect. Sumber tanpa URL tidak diperiksa.

`publikasi:crawl-sinta` dijadwalkan setiap hari pukul 04.00 UTC (11.00 WIB), setelah `m19:tick` pukul 03.00 UTC. `SINTA_CRAWL_ENABLED=false` menonaktifkan pemeriksaan sampai diaktifkan di lingkungan produksi. Batas bawaan SINTA adalah 15 pemeriksaan per hari. Kedua pemantau berbagi batas total 20 pemeriksaan jurnal per hari, termasuk eksekusi manual. Sumber SINTA yang selesai atau gagal dijadwalkan kembali 60 hari kemudian. Dengan 924 URL dan 15 per hari, satu putaran memerlukan sekitar 62 hari bila semua URL dapat diperiksa dan tidak ada sumber M19 yang memakai slot tambahan.

Tabel `sinta_page_states` menyimpan antrean dan hash terakhir, `sinta_page_checks` mencatat setiap percobaan dan kode kegagalan, dan `sinta_page_candidates` menyimpan kutipan cakupan yang perlu ditinjau. Admin dapat membaca hasil terbaru di `/admin/agen-jurnal` dan menandai kutipan diperiksa atau ditolak. Halaman jurnal tidak digunakan untuk mengubah rank SINTA, status Scopus, atau isi katalog. Bukti yang diterima admin pun tidak langsung mengubah rekomendasi.

## Pemasangan

1. Cadangkan database dan runtime sebelum migrasi additive `2026_10_15_000002_create_sinta_page_monitor_tables.php`. Tiga tabel baru kosong; tidak mengubah data impor.
2. Pasang kode, jalankan migrasi tersebut, dan pastikan `php artisan publikasi:crawl-sinta` mengembalikan `disabled` sebelum aktivasi.
3. Periksa `php artisan schedule:list` dan cron `schedule:run`; jangan menambah cron lain. Atur `SINTA_CRAWL_ENABLED=true` di `.env`, lalu `php artisan config:cache`.
4. Jalankan `php artisan publikasi:crawl-sinta` sekali dan bandingkan hitungan `checked`, `errors`, dan kandidat dengan `sinta_page_checks`. Audit domain dan hasil pertama. Jika mayoritas URL gagal, matikan sakelar dan periksa URL/robots sebelum melanjutkan.
5. Besok setelah 11.00 WIB, periksa jumlah run hari tersebut dan alasan kegagalan. Jangan menandai deployment teruji otomatis sebelum cron tersebut benar-benar berjalan.

Rollback kode dapat dilakukan dengan mengembalikan rilis lama dan menonaktifkan sakelar. Biarkan tabel bukti tetap ada jika sudah berisi data. Jangan rollback migrasi produksi sesudah pemantauan menghasilkan catatan.
