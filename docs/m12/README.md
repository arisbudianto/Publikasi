# M12 — Integritas jurnal

## Batas fitur

- Checklist delapan kriteria membantu pengguna memeriksa identitas, dewan editor, peer review, biaya, klaim indeksasi, janji waktu/penerimaan, undangan massal, dan identitas penerbit.
- Hasil deterministik memakai `JournalIntegrityEvaluator::VERSION`. Jawaban `unknown` tercatat terpisah dan mencegah hasil “tidak ditemukan masalah”. Teks hasil bukan vonis jurnal atau label “predator”.
- Pengunjung tidak menyimpan lookup atau jawaban. Akun login hanya menyimpan jawaban jika kotak opt-in dicentang; lookup mentah tidak dicatat. Pengguna dapat melihat dan menghapus jawaban simpanan di halaman akun.
- Halaman `/periksa/discontinued` mencari snapshot discontinued berdasarkan ISSN atau nama, termasuk sumber yang tidak ada di katalog kurasi. Detail jurnal dan hasil pencocokan memberi peringatan saat ISSN cocok pada snapshot current.
- Tahun cakupan menunjukkan kolom coverage dalam daftar; bukan tanggal pasti keputusan discontinued. UI tidak menyatakan artikel sebelum akhir cakupan tidak terindeks.

## Sumber dan pembaruan data Scopus

Elsevier menerangkan bahwa Source Title List mencakup daftar discontinued dan diperbarui bulanan: https://www.elsevier.com/products/scopus/content. Daftar harus diunduh oleh operator dari Scopus/Elsevier. Aplikasi tidak melakukan scraping dan tidak memuat data contoh.

Ekspor sheet “Discontinued Titles” sebagai CSV, lalu susun kolom sesuai `scopus-discontinued-template.csv`: `source_title,issn,discontinued_at,coverage_start_year,coverage_end_year,reason`. Masukkan ISSN valid per baris. Jika satu sumber memiliki lebih dari satu ISSN, buat satu baris untuk tiap ISSN. Isi `discontinued_at` hanya bila tanggal keputusan tercantum secara eksplisit. Jangan mengubah tahun cakupan menjadi tanggal discontinued atau menebak tahun/alasan yang tidak tertulis.

Di cPanel, upload CSV di luar document root, lalu jalankan preview:

```sh
php artisan publikasi:import-scopus-discontinued /home/speakver/private/scopus-discontinued.csv --source-as-of=YYYY-MM-DD
```

`source-as-of` adalah tanggal snapshot pada daftar resmi dan tidak boleh lebih lama daripada snapshot terkini yang telah diimpor. Preview menampilkan jumlah baris, baris invalid, dan SHA-256; tidak ada perubahan database. Perbaiki semua error sebelum apply. Simpan backup database lebih dulu, lalu jalankan perintah yang sama dengan `--apply` hanya setelah memeriksa file dan tanggalnya:

```sh
php artisan publikasi:import-scopus-discontinued /home/speakver/private/scopus-discontinued.csv --source-as-of=YYYY-MM-DD --apply
```

Impor mengganti penanda `is_current` dalam satu transaksi dan mempertahankan snapshot sebelumnya. Jalankan kembali preview untuk tanggal/file yang sama bersifat aman.

## Rujukan checklist

- Think. Check. Submit., Journal checklist: https://thinkchecksubmit.org/journals/
- Principles of Transparency and Best Practice in Scholarly Publishing: https://doi.org/10.24318/cope.2019.1.12
- COPE Core Practices: https://publicationethics.org/core-practices
- ISSN Portal: https://portal.issn.org/
