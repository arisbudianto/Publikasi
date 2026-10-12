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

## Ekspansi semua bidang (12 Oktober 2026)

Release `6e0aa10d133f5c674ba1168552b6c43f92fb0f6a` memperluas direktori agar menampilkan seluruh 999 sumber, termasuk 691 yang tidak berlabel Pendidikan dan 75 rekaman tanpa nama. Pencarian menerima ID SINTA agar rekaman tanpa nama dapat ditemukan. Alur admin dapat menyiapkan draft lintas-subjek jika nama tersedia dan scope telah diperiksa. Lima jurnal terkurasi tetap ditampilkan di katalog terpisah dan tidak berubah.

Enam file runtime lama dicadangkan sebelum perubahan di `/home/speakver/publikasi_backups/pre-sinta-all-fields-20261012-1710.tar.gz`; SHA-256 `0cb271aa4608a846ba6ec6a6dcdfcb1ceba30aeefda1225380637b638c4df549`. Source checkout fast-forward ke `6e0aa10`, working tree bersih. Deploy mengganti controller dan Blade views dalam maintenance singkat; Blade cache dibangun ulang, aplikasi kembali live. Tidak ada perubahan migrasi atau baris database.

Verifikasi sesudah deploy: homepage menyebut lima jurnal katalog terkurasi dan 999 sumber lintas bidang; `/sumber-jurnal` menampilkan total 999 dan kategori semua bidang; `/sumber-jurnal?q=15` mengembalikan satu rekaman tanpa nama dengan ID SINTA 15. Hitungan database: sources999, education308, other691, unnamed75, journals5. Tes lokal sesudah patch: 66 tests / 506 assertions PASS; Pint, route/view cache, diff check PASS.

## Integrasi keputusan akreditasi SINTA (12 Oktober 2026)

Release `4dcfa5d1d173653bbe58d58b66ec137606b214af` menambahkan direktori keputusan SINTA Periode 3 Tahun 2025 dari SK `355/DST/D.D1/HM.01.01/2026`, bertanggal 24 Juli 2026. Dataset 2.697 keputusan disimpan additive dengan fingerprint PDF `76336ef18a3ef7fb71080502db80b7fd0164ac67848878cfe176cd98e8e7395f`. Kecocokan ke snapshot hanya melalui e-ISSN yang valid; tidak mengubah label historis atau isi jurnal kurasi.

Sebelum deployment, file runtime lama dicadangkan di `/home/speakver/publikasi_backups/pre-accreditation-20261012-180457.tar.gz`; SHA-256 `d3206b7cc3c1109c0624f3cedc4a71990e9fa3bf05923a3f55dd0f265168d8df`. Pemilik aplikasi mengonfirmasi ekspor backup database lewat phpMyAdmin sebelum migrasi; nama file dan checksum backup database tidak dicatat di sesi ini. Migrasi `2026_10_12_000008_create_journal_accreditations_table` selesai, route dan Blade cache dibangun ulang, dan aplikasi kembali live.

Dry-run memvalidasi 2.697 baris dengan rank SINTA 1–6 berjumlah 28, 192, 658, 1.130, 610, dan 79. Ditemukan 83 kecocokan sumber melalui ISSN valid, 2.592 tidak cocok, 18 ISSN checksum tidak valid, 4 tanpa ISSN, dan tidak ada kecocokan ke lima jurnal kurasi. Hitungan produksi setelah apply: `journal_accreditations=2697`, `journal_source_id` terisi 83, `journal_id` terisi 0; status `matched=83`, `unmatched=2592`, `invalid_issn=18`, `missing_issn=4`. Data lama tetap `journal_sources=999`, `journals=5`, `users=1`.

Smoke test publik membuka `/akreditasi-sinta`, menampilkan 2.697 keputusan, dan mencari ISSN `23556544`. Hasil tunggal “Geoplanning: Journal of Geomatics and Planning” tampil pada SINTA 1 dan terhubung ke rekaman sumber `/sumber-jurnal/932`. Tes lokal: 69 tests / 556 assertions PASS; Pint, route/view cache, dan diff check PASS. Verifikasi ini membuktikan impor serta keterhubungan identitas ISSN untuk data yang cocok, bukan pemeriksaan independen terkini atas seluruh status akreditasi.
