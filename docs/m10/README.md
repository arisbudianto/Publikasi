# M10: Observasi terstruktur dan skala katalog

## Ruang lingkup rilis

- Menambah kolom terstruktur yang nullable pada `journal_observations`; data lama dan teks `value` tidak diubah atau ditebak ulang.
- Menyimpan skema/peringkat, nilai biaya dalam unit minor dan mata uang, jalur biaya, waiver, bahasa, frekuensi, dan periode berlaku.
- Menyimpan ISSN cetak, elektronik, dan ISSN-L tambahan dengan sumber, tanggal pemeriksaan, audit, dan penarikan yang mempertahankan riwayat. Kolom `journals.issn` tetap menjadi ISSN utama.
- Menyediakan impor CSV maksimal 1 MB dan 500 baris, preview 15 menit, validasi seluruh baris, satu transaksi saat diterapkan, dan tidak ada commit parsial.
- Menghubungkan observasi CSV melalui ISSN utama atau identifier aktif. Hubungan ambigu ditolak untuk pemeriksaan manual.
- Menggunakan tabel `journal_accreditations` yang sudah ada sebagai sumber keputusan SINTA. Tidak menyalin peringkat SINTA atau mengubah 999 rekaman sumber menjadi jurnal kurasi.
- Menyediakan `publikasi:link-sinta-accreditations` untuk dry-run dan tautan additive berdasarkan ISSN e-ISSN valid yang cocok ke tepat satu jurnal utama/identifier. Perintah tidak menulis tanpa `--apply`.
- Menyimpan embedding scope jurnal publik sebagai float32 biner berdasarkan model dan hash scope. Hash baru dibuat pada pencocokan berikutnya saat scope telah berubah. Judul, abstrak, konteks, dan embedding naskah tetap tidak disimpan.
- Membatasi batch embedding scope hingga 50 per panggilan. `SEMANTIC_CATALOG_LIMIT` tetap 50 secara default; kenaikan batas menunggu pengukuran batas waktu dan sumber daya PHP di cPanel (D16).

## Aturan data

- Informasi biaya belum diketahui bukan nol. Nilai `amount_minor=0` hanya diterima dari sumber yang secara eksplisit menyatakan gratis.
- Nilai nominal tetap disimpan dan ditampilkan sebagai unit minor sesuai mata uang; `value` tetap menjadi ringkasan yang terbaca dan sumbernya tetap wajib.
- Peringkat keputusan SINTA mempertahankan tabel, periode, nomor SK, tanggal keputusan, dan fingerprint dokumen yang sudah ada.
- Tidak ada scraping baru, impor massal tanpa preview, atau promosi otomatis `journal_sources` menjadi jurnal terbit.

## Deployment

Migrasi `2026_10_12_000009_expand_structured_journal_data` bersifat additive. Backup database dan file runtime harus tersedia sebelum migrasi produksi. Terapkan migrasi sebelum kode baru, lalu uji rute katalog, form kurasi, preview CSV, dan perintah dry-run. Jangan jalankan `migrate:rollback` sebagai rollback kode setelah observasi, identifier, atau embedding tersimpan; rollback aplikasi harus mempertahankan data M10.

Impor dan penautan ulang keputusan SINTA adalah operasi terpisah. Setelah deployment, jalankan `php artisan publikasi:link-sinta-accreditations --no-ansi` terlebih dahulu. Terapkan perubahan hanya setelah hitungan dry-run diperiksa dengan `--apply`.

## Batas kesiapan

Pengaturan default AI tetap 50 jurnal sampai waktu eksekusi, memori, batas proses, dan respons provider diuji pada cPanel. Database embedding memakai MySQL `LONGBLOB`; verifikasi MariaDB produksi diperlukan saat migrasi. Evaluasi kualitas peringkat tidak termasuk modul ini.
