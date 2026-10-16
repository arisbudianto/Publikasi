# Sinkronisasi data jurnal dari crawler

Crawler SINTA, Scopus, dan halaman katalog yang aktif menyimpan pemeriksaan halaman. Ketika ekstraktor menemukan informasi dan identitas cocok, informasi langsung masuk ke katalog. Hasil `synced` pada pemeriksaan menunjukkan bahwa penulisan terjadi. Perubahan laman tanpa informasi yang cocok tidak mengubah katalog.

- SINTA: jurnal baru hanya diterbitkan bila nama, penerbit, ISSN dengan checksum valid, situs HTTPS, dan keputusan akreditasi SINTA 1–4 yang cocok tersedia. URL hasil crawl harus berdomain sama dengan situs jurnal. Jurnal berstatus arsip tidak diaktifkan kembali.
- Scopus: halaman yang diaktifkan admin hanya memperbarui jurnal aktif yang sudah ada, dengan satu kecocokan ISSN dan domain. Status indeks dan quartile tetap berasal dari snapshot, bukan hasil crawl.
- Halaman katalog: cakupan, klaim biaya, atau waktu proses pada sumber yang diaktifkan diperbarui otomatis. Klaim biaya dan waktu diberi status `publisher_claim`; angka yang belum terstruktur tidak dianggap biaya nol atau bukti verifikasi independen.
- Semua perubahan rekaman jurnal memakai `catalog_audits`. Pemeriksaan SINTA dan Scopus menandai `synced`; kutipan yang identitasnya tidak cocok tetap menjadi kandidat untuk pemeriksaan admin. Perubahan laman, robots error, dan HTTP error tidak memublikasikan jurnal.

Jalankan migrasi `2026_10_16_000001_add_crawler_sync_status.php` sebelum mengganti kelas crawler dan dashboard. Jadwal penemuan calon dari snapshot lama tidak lagi berjalan; perintah manual lama tetap tersedia untuk data historis. Proses ini hanya berlaku pada pemeriksaan berikutnya, tanpa menerapkan ulang hasil crawl lama.
