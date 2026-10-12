# Bukti deployment snapshot SINTA

Tanggal: 12 Oktober 2026 (Asia/Jakarta)  
Repo: `arisbudianto/Publikasi`  
Release aplikasi: `1fec71069e915c84fcbecc7de81c492d68b0af85`  
Production: `publikasi.speakverse.id`  
Source checkout: `/home/speakver/publikasi_m00_source`  
Application root: `/home/speakver/publikasi.speakverse.id`

## Perubahan dan backup

- Checkout source di-fast-forward dari `918fdc0` ke `1fec710`; output menunjukkan working tree bersih.
- Empat file existing yang berbeda dicadangkan sebelum deploy di `/home/speakver/publikasi_backups/pre-sinta-20261012-1648.tar.gz`; SHA-256 `ef3a890e61e5a805c9e8722e0cf224c868dc9f59b78f626e5638392f6c20c9be`. Arsip berisi admin/catalog Blade dan routes console/web.
- Tidak menyalin `.env`. File migrasi 000007 menambah tabel `journal_sources`; tabel kosong diverifikasi sebelum impor, lima jurnal lama tetap ada.
- File controller/model/service, dataset dan manifest, view, serta routes dipasang. Route cache dan view cache dibangun ulang dan maintenance mode dilepas.

## Bukti database produksi

- Dry run: `total=999`, `education=308`, `unnamed=75`, `new=999`, `existing=0`.
- Apply: `created=999`, `skipped=0`.
- Apply ulang: `created=0`, `skipped=999`.
- Hitungan akhir: `journal_sources=999`, Pendidikan `308`, tanpa nama `75`, `journals=5`, `users=1`.
- Fingerprint raw snapshot: `b1c79730dd707e393ccecf6c4a6940ce7ad5d99ec6a236e3d975f5a009ca83eb`.

## Smoke publik

- `GET /sumber-jurnal`: halaman “Database sumber jurnal pendidikan”, menampilkan 308 hasil dengan peringatan bahwa snapshot 20 Maret 2025 belum diverifikasi ulang.
- `GET /sumber-jurnal?q=24423904`: satu hasil “Jurnal Pendidikan Sains”.
- `GET /sumber-jurnal/1`: detail sumber, label historis, ISSN, tanggal capture, serta judul artikel tampil. Halaman memberi penanda belum dikurasi dan snapshot mungkin usang.
- Route list mendaftarkan index/detail publik serta prepare/promote yang dibatasi admin.

## Batas bukti

Seluruh verifikasi tersebut membuktikan pemasangan fitur dan impor additive; bukan status akreditasi terkini atau kebenaran semua identitas sumber. `publikasi:health`, authenticated production writes, database backup/restore, dan rollback rehearsal tidak dijalankan. Jangan menghapus `journal_sources` setelah impor. Katalog terkurasi tidak berubah dan hasil impor belum masuk ke matcher sebelum verifikasi editorial.
