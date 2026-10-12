# Dataset pemilik: archive.zip

## Fakta input

Dua JSON memiliki journals identik; gunakan all_sinta_journals.json sekali, bukan menggandakan999 menjadi1998. Snapshot20 Maret2025; timezone timestamp tidak dinyatakan. SHA256 raw b1c79730dd707e393ccecf6c4a6940ce7ad5d99ec6a236e3d975f5a009ca83eb.

999 record, 308 berlabel Education (termasuk multi-bidang),75 tanpa nama,113 tidak memiliki salah satu ISSN yang lolos checksum. ID664 gagal diambil dan tidak dibuat-buat. Kategori pendidikan dipilih berdasarkan label yang tersedia, bukan inferensi dari nama. Identitas cetak/elektronik tetap perlu verifikasi walau checksum valid.

## Mapping dan kebijakan

Tabel journal_sources menyimpan source_id SINTA unik, nama, institution sebagai publisher, subject, label SINTA historis, p/e ISSN valid, raw identifiers untuk audit, URL sumber dan website asli sebagai teks, captured_at, education, maksimal10 judul artikel perrecord, fingerprint dataset serta hubungan draft opsional. Tidak mengimpor metrik bernama impact_factor sebagai JIF, tidak menganggap label SINTA2025 masih berlaku, tidak memakai gambar/profil pribadi Google Scholar, tidak mengimpor fulltext atau klaim akreditasi artikel. Mojibake judul dipertahankan dan diberi peringatan; tidak diperbaiki dengan tebakan. DOI/URL artikel tidak digunakan sebagai evidence terverifikasi dari snapshot.

Dataset normalized terbagi40 part JSON dengan hash tiap part dan manifest total. File raw tetap attachment pemilik; part repo hanya subset metadata yang digunakan aplikasi. Tidak ada crawl baru. Asal pemilik/source URL diketahui; lisensi reuse pihak sumber tidak disebutkan dalam upload. Jangan menggambarkannya sebagai dataset terbuka berlisensi tertentu.

Impor: migration000007 menambah tabel kosong. `php artisan publikasi:import-sinta` dry run; `--apply` hanya firstOrCreate999 sumber dalam transaksi, tidak menimpa katalog, observasi, atau snapshot fingerprint lain. Replay skip, bukan update. Record tanpa nama tetap tersimpan tetapi tidak tampil publik. Tidak ada penghapusan/bulk update data produksi. Index source_id, education danISSN; hasil publik paginate18.

## Akses dan kurasi

`/sumber-jurnal`: cari nama/penerbit/ISSN/judul artikel, default pendidikan. Detail menampilkan identitas, tanggal lama, label historis dan judul artikel yang belum diverifikasi. Katalog menautkan pencarian dataset, mempertahankan katalog scope terkurasi terpisah. Record tanpa pendidikan/nama404 di publik.

Admin Prepare draft melalui auth+RequireAdmin, konfirmasi sumber dan aturan Catalog (checksumISSN, HTTPS URL scope/jurnal, tanggal diperiksa, ringkasan scope). Selalu draft; tidak menimpa ISSN existing. Hanya source education bernama dapat dipromosikan. Snapshot tetap historis setelah draft dibuat. CatalogAudit merekam pembuatan draft. Mutasi source->journal_id hanya pada tindakan admin eksplisit, tidak saat impor.

Data impor belum masuk BasicMatcher/SemanticMatcher sampai scope benar-benar dikurasi dan jurnal dipublikasikan. Dataset tidak memiliki aims/scope resmi atau biaya/waktu. Memublikasikannya otomatis akan mengarang kelengkapan data serta mematikan batas katalog AI50. Batas AI existing50 tetap berlaku; pengembangan index embedding/preselection dibutuhkan jika curated catalog nanti melampaui50.

## Verifikasi dan rollback

69 tes/563 assertions PASS lokal PHP8.3/SQLite, termasuk dataset asli999/308/75, replay, checksum parts dan formatISSN, fingerprint conflict, escapedXSS, public eligibility, permission admin, draft-only dan duplicateISSN. Tests tidak dijalankan pada database produksi. Live import/runtime dicatat dalam DEPLOYMENT_EVIDENCE.md setelah selesai.

Rollback kode memulihkan routes/console/index views dan mempertahankan tabel serta999 record. Tidak menjalankan drop atau migrate:rollback untuk rollback kode rutin. Scope data baru append-only import; tidak ada backfill ke data terkurasi existing.

## Keputusan akreditasi Periode 3 Tahun 2025

Lampiran `pemberitahuan.pdf` berisi salinan SK Direktur Jenderal Sains dan Teknologi Nomor 355/DST/D.D1/HM.01.01/2026, ditetapkan 24 Juli 2026, dan surat pemberitahuan Nomor 862/DST/D.D1/HM.01.01/2026 tanggal 28 Juli 2026. Data hasil ekstraksi disimpan pada `database/datasets/sinta-accreditation-2025-period-3`; fingerprint PDF sumber `76336ef18a3ef7fb71080502db80b7fd0164ac67848878cfe176cd98e8e7395f`.

Dataset berisi 2.697 keputusan: peringkat 1=28, 2=192, 3=658, 4=1.130, 5=610, dan 6=79. Sebanyak 2.693 baris memuat e-ISSN, 2.675 lolos checksum ISSN, 18 tidak lolos, dan 4 tidak memiliki e-ISSN yang terbaca. Satu baris berjenis keputusan `unknown` karena teks sumber yang diekstrak tidak memuat label keputusan lengkap; peringkat bagian dan rentang volumenya dipertahankan tanpa menebak jenis keputusan.

Migration `000008` membuat tabel tambahan `journal_accreditations`. `php artisan publikasi:import-sinta-accreditation` hanya dry-run; `--apply` menambahkan baris berdasar nomor item SK, serta menautkan ke `journal_sources` atau `journals` hanya jika e-ISSN cocok persis dan checksum valid. Pada fixture 999 sumber, 83 keputusan ditautkan ke sumber lama. Nama, label SINTA historis, dan jurnal terkurasi lama tidak ditimpa. Baris tanpa e-ISSN/checksum valid disimpan untuk pencarian tetapi tidak ditautkan otomatis. Direktori publik `/akreditasi-sinta` memisahkan hasil keputusan ini dari snapshot 20 Maret 2025.

Uji lokal mencakup hitungan seluruh 2.697 baris, replay idempotent, preservasi 999 sumber dan 5 jurnal terkurasi, pencocokan tepat 83 sumber, serta pencarian publik. Data keputusan menyatakan masa berlaku menurut volume dan nomor terbitan; daftar ini tidak membuktikan bahwa data SINTA daring telah dimutakhirkan atau status berlaku saat ini.

Direktori keputusan menampilkan pintasan kategori SINTA 1–4 dengan jumlah keputusan yang dihitung dari tabel, masing-masing 28, 192, 658, dan 1.130 pada rilis dataset ini. Kategori membuka filter peringkat yang sama; SINTA 5 dan 6 tetap dapat dicari melalui filter lengkap. Angka menunjukkan rekaman keputusan dalam dokumen, bukan klaim jumlah jurnal unik yang telah diverifikasi.
