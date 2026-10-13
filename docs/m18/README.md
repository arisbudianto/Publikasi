# M18 Literasi dan usulan koreksi

Panduan publik hanya memuat artikel yang diterbitkan admin. Admin dapat menulis draf teks biasa, menandai tanggal tinjau, dan menerbitkannya. Tidak ada artikel atau fakta yang diisi otomatis oleh migrasi. Artikel memerlukan pemeriksaan editorial sebelum rilis.

Pengguna yang login dapat mengusulkan jurnal baru atau koreksi untuk jurnal published. URL sumber wajib HTTPS. Pengiriman dibatasi tiga per jam per sesi/IP melalui throttle Laravel. Usulan baru tidak pernah langsung mengubah jurnal, observasi, atau indeksasi. Pengguna hanya melihat usulan miliknya dan statusnya.

Admin meninjau usulan dalam antrean kurasi. Status `new` menuju `reviewed` atau `rejected`, lalu `reviewed` menuju `applied` setelah tindakan manual. Setiap transisi menulis event. Untuk jenis observasi M10, tombol siapkan membuka form observasi dengan nilai dan sumber usulan; admin tetap mengisi label, struktur, dan bukti pemeriksaan. Penerbitan observasi dan perubahan status usulan berada dalam transaksi database yang sama dan menulis audit katalog. Untuk jurnal baru, scope, dan ISSN, admin memakai alur katalog/identifier yang sudah ada lalu mencatat tindakan secara manual pada usulan dengan ID jurnal dan catatan. Catatan tersebut bukan penerbitan data otomatis.

Tidak ada surel atau AI pada M18. Teks pengguna ditampilkan escaped, konten panduan memakai teks biasa. Artikel panduan belum menjadi sumber M17 sampai benar-benar diterbitkan dan ditinjau.

## Rilis cPanel

Migrasi `2026_10_13_000004_create_m18_guides_and_proposals.php` menambah tiga tabel tanpa mengubah tabel lama. Sebelum menjalankan migrasi, simpan dump database dan backup berkas runtime yang berubah, serta verifikasi arsip. Salin migrasi dari merge commit yang persis, jalankan `migrate --force --path=...`, cek status dan tabel. Kemudian salin berkas runtime, cache route dan view, dan uji `/panduan` saat belum ada artikel, redirect login pada `/akun/usulan`, dan alur usulan dengan data sintetik. Jangan jalankan PHPUnit pada database produksi. Untuk rollback kode, jangan drop tabel berisi usulan; pertahankan data dan nonaktifkan link melalui rilis kode sebelumnya hanya setelah pemeriksaan kompatibilitas.
