# Daftar sumber Scopus

Formulir `/cocokkan` memakai tabel sumber SINTA dan Scopus untuk kandidat jurnal terindeks. Scopus dibatasi pada jenis `Journal`, status `Active`, dan tanpa penanda discontinued. SINTA dibatasi pada keputusan akreditasi resmi yang terhubung melalui ISSN valid dengan tahun akhir yang belum lewat. Irisan kata pada nama, subjek, atau judul artikel merupakan petunjuk awal; scope lengkap, status indeks saat submit, biaya, dan masa review tetap harus diperiksa.

Halaman `/sumber-scopus` membaca snapshot *Scopus Source Title List* September 2026 yang diberikan operator. Data tersimpan terpisah dari sumber SINTA dan katalog kurasi. `Active`, `Inactive`, `Coverage`, dan `Discontinued by Scopus` mengikuti kolom sumber; status snapshot tidak membuktikan indeksasi terkini atau artikel tertentu. Daftar accepted titles bukan bagian dari daftar sumber yang sudah tercatat.

Workbook asli tetap disimpan secara privat. Ekstrak sheet `Scopus Sources Sep. 2026` ke CSV UTF-8 dengan header berikut, sesuai urutan:

```
source_record_id,source_title,issn,eissn,status,coverage,discontinued,source_type,publisher,asjc_codes
```

Kolom `discontinued` bernilai 1 hanya bila kolom `Titles Discontinued by Scopus` berisi `Discontinued by Scopus`. ISSN dan EISSN dipertahankan sebagai teks 8 karakter agar nol di depan tidak hilang. Jangan menyalin daftar accepted titles sebagai jurnal yang sudah terindeks.

Unggah CSV di luar document root. Pratinjau dan verifikasi jumlah, error, serta SHA-256 sebelum impor:

```sh
php artisan publikasi:import-scopus-sources /home/speakver/private/scopus-sources-sep-2026.csv --snapshot-month=2026-09
```

Sesudah backup database dan review pratinjau, tambahkan `--apply`. Impor satu transaksi menandai snapshot sebelumnya tidak current dan mempertahankan rekaman historis. Untuk memperbarui bulan berikutnya, gunakan snapshot resmi yang lebih baru, bukan hasil scraping. CSV dan workbook tidak disimpan di repository publik.
