# M02 Filter dan perbandingan jurnal

Katalog publik dapat difilter dengan bidang pendidikan dan penerbit. Filter ketersediaan informasi harus memilih jenis (indeksasi/biaya/waktu) bersama status tersedia/belum diketahui. Informasi tersedia tidak menyatakan status indeksasi aktif, biaya gratis, atau verifikasi independen. Data numerik belum terstruktur; filter rentang biaya/durasi ditunda, tidak dikarang dari teks.

Pilihan satu sampai tiga jurnal disimpan pada query URL, bertahan melalui pencarian/filter/pagination dan dapat dihapus. Tidak memerlukan akun atau JavaScript. Pilihan tidak disimpan setelah pengguna membuka URL tanpa ids. /bandingkan menampilkan pilihan sesuai urutan, penerbit, ISSN, bidang, scope, sumber dan tanggal serta seluruh observasi berikut periode/status verifikasi.

Batas tiga, duplikasi ID dan tipe input divalidasi server-side. Jurnal draft/arsip/tidak ada ditolak (404), termasuk ketika admin membuka perbandingan publik. Nilai belum diketahui bukan nol. Tabel dapat digeser horizontal pada layar kecil.

TESTED: PHP 8.3, 16 tests / 93 assertions PASS: regresi M01, gabungan filter, hasil kosong, status unknown, provenance, urutan pilihan, batas tiga, input invalid, draft/arsip, pagination, escaping HTML.
NOT TESTED: produksi sebelum deployment; Safari/perangkat seluler fisik.
DATA IMPACT: READ_ONLY; tidak ada migration, perubahan dependency, seed, credential atau data produksi.
ROLLBACK: kembalikan lima file aplikasi M02 ke commit 440bbac dan rebuild route/view cache; database tidak berubah.
