# Crawler halaman jurnal dalam snapshot Scopus

Menu admin `/admin/crawler-scopus` mencari sumber dari snapshot Scopus yang sudah diimpor. Admin memasukkan alamat HTTPS resmi untuk jurnal yang cocok dan memeriksa URL sebelum mengaktifkannya. Tidak ada alamat penerbit yang ditebak dari nama jurnal. Crawler mengambil halaman publik sesuai robots.txt, tanpa Scopus API key, memakai `JournalPageFetcher` yang membatasi DNS, pengalihan, ukuran, dan waktu permintaan.

Jalankan migrasi `2026_10_15_000004_create_scopus_page_monitor_tables.php` sebelum memasang kode runtime. Scheduler menjalankan `publikasi:crawl-scopus-journals` pukul 03.30 UTC. Secara bawaan `SCOPUS_CRAWL_ENABLED=false`; setelah URL sumber diperiksa, tetapkan `SCOPUS_CRAWL_ENABLED=true`. `SCOPUS_CRAWL_DAILY_LIMIT` bawaan 5, maksimum 10. Satu jurnal diperiksa paling banyak sekali per hari; kuota gabungan M19, SINTA, dan Scopus adalah 20 pemeriksaan. Halaman yang sukses dijadwalkan ulang setelah 30 hari. Periksa riwayat error di menu admin.

Kutipan dari halaman penerbit hanya menjadi kandidat pemeriksaan admin. Status indeks, quartile, keputusan SINTA, dan katalog tidak diperbarui oleh crawler. Status indeks tetap mengacu pada snapshot berlisensi dan pembaruan resmi yang diperiksa secara terpisah.
