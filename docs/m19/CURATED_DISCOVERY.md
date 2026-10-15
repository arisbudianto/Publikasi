# Agen katalog terkurasi

`publikasi:discover-curated` berjalan pukul 04.30 UTC setiap hari setelah pemantauan halaman SINTA. Perintah hanya membaca data lokal dan mendaftarkan maksimal 20 calon baru per hari dari rekaman sumber SINTA yang cocok dengan keputusan akreditasi SINTA 1 sampai 4. Syaratnya nama dan alamat HTTPS tersedia, ISSN keputusan valid, dan jurnal dengan ISSN yang sama belum berada di katalog. URL snapshot belum berarti situs resmi sudah terverifikasi.

Admin melihat calon di `/admin/agen-jurnal`, memeriksa keputusan dan situs jurnal, kemudian membuka alur draft yang sudah ada. Nama, ISSN, scope dan sumber tetap harus diverifikasi sebelum draft dibuat dan diterbitkan. Admin juga dapat menolak usulan dengan alasan. Usulan yang ditolak tidak dimunculkan lagi oleh jadwal otomatis.

Untuk jurnal yang sudah terkurasi, M19 memantau halaman sumber resmi setiap hari. Perubahan menjadi kutipan dan tautan bukti yang menunggu tinjauan admin. Persetujuan kutipan tidak mengubah katalog; admin memperbarui data melalui alur kurasi dan audit yang berlaku. Agen tidak mengisi biaya, peluang diterima, quartile Scopus, atau status akreditasi dari dugaan.

Migrasi `2026_10_15_000003_create_curation_leads_table.php` hanya menambah tabel antrean. Tidak ada backfill otomatis saat migrasi. Jalankan perintah sekali setelah deploy untuk mengisi antrean pertama, lalu verifikasi jumlah calon dan halaman admin. Rollback kode dapat dilakukan tanpa menghapus tabel dan catatan tinjauan.
