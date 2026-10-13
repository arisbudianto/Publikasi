# M14 pemeriksa authorship

## Cakupan

- Menerima 1 sampai 30 kontributor, nama atau inisial, jawaban empat kriteria ICMJE, dan peran CRediT.
- Menandai orang yang memenuhi semua syarat, belum memenuhi semua syarat, atau memiliki jawaban yang perlu ditinjau kembali.
- Mengingatkan bahwa funding acquisition, general supervision, atau project administration saja tidak memenuhi kontribusi substantif ICMJE.
- Membuat draf pernyataan kontribusi CRediT dan usulan urutan sementara berdasarkan jumlah peran yang dipilih, tanpa pembobotan.
- Hanya memproses input untuk permintaan saat itu. Tidak menyimpan data di database, sesi, atau log dan tidak menggunakan AI.

M14 tidak membuat keputusan authorship yang mengikat, mengukur besarnya usaha, atau menggantikan kebijakan jurnal/institusi. Urutan final ditentukan bersama oleh kelompok penulis dan dapat berbeda menurut bidang.

## Aturan dan sumber

Versi aturan aplikasi: `2026-10-v1`.

- ICMJE menyatakan authorship memerlukan keempat kriteria. Kontributor yang belum memenuhi semuanya tidak dicantumkan sebagai penulis dan dapat diakui kontribusinya; pendanaan, supervisi umum, dan dukungan administratif saja tidak cukup. ICMJE juga menyatakan urutan byline ditentukan bersama oleh kelompok penulis. https://www.icmje.org/recommendations/browse/roles-and-responsibilities/defining-the-role-of-authors-and-contributors.html
- CRediT memiliki 14 peran kontribusi dan bukan alat untuk menentukan authorship. Nama peran ditampilkan bilingual; sumber: https://credit.niso.org/contributor-roles/ dan https://credit.niso.org/contributor-roles-defined/ . Taksonomi CRediT berlisensi CC BY 4.0; atribusi ke CRediT/NISO dicantumkan di sini.

## Verifikasi

- Tes fitur M14 meliputi empat kriteria, kriteria yang belum terpenuhi, peran funding/supervision saja, validasi batas input, HTML escaping, serta tiadanya penyimpanan data.
- Tidak ada migrasi database atau dependency baru.
- Suite lokal pada baseline pasca-M13: 103 tes / 752 assertions lulus; tes terarah M14: 8 tes / 47 assertions lulus.
- Pint, route cache, dan Blade view cache berhasil dibangun lokal. GitHub Actions tetap dijalankan pada branch M14 sebelum merge.
- Pengujian produksi dicatat sesudah deploy dan smoke test `/periksa/authorship`.
