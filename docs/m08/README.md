# M08 — Pelacak submission dan matriks respons reviewer

## Perilaku

`/akun/submission` adalah ruang pribadi. Pengguna memilih jurnal terbit, membuat pelacak dalam tahap persiapan, mencatat nomor pengajuan, tanggal kejadian, tenggat, serta catatan. Tidak ada pengiriman artikel atau sinkronisasi OJS/editor. Kalender tanggal mengikuti WIB; waktu audit tersimpan UTC.

Alur: persiapan ke diajukan atau ditarik; diajukan ke review/revisi/diterima/ditolak/ditarik; review ke revisi/diterima/ditolak/ditarik; revisi ke diajukan/diterima/ditolak/ditarik. Status akhir tidak dibuka kembali. Gunakan pelacak baru untuk pengajuan baru. Timeline perubahan status append-only melalui aplikasi; tanggal tidak boleh mundur atau berada di masa depan. Catatan, judul dan tenggat masih dapat diperbarui setelah status akhir.

Matriks: putaran1–99, alias reviewer, komentar, respons penulis, lokasi revisi, status belum ditangani/ditangani. Ditangani mewajibkan respons dan lokasi, bukan persetujuan reviewer. Maksimal500 baris per pelacak. Daftar, timeline (terbaru dahulu) dan matriks dipaginasi. Pemilihan jurnal dibatasi100 hasil nama dengan pencarian. CSV UTF-8 berisi matriks, dengan awalan formula spreadsheet dinetralkan.

## Batas akses dan data

Semua endpoint memeriksa kepemilikan, termasuk ekspor dan kombinasi submission/baris. Admin tidak dapat membaca pelacak pengguna lain. Versi optimistic concurrency menolak perubahan usang409; transaksi dan row-lock digunakan pada penulisan. Tidak ada upload naskah, lampiran, email reviewer atau AI baru. Gunakan alias reviewer dan hindari data sensitif yang tidak diperlukan. Timeline adalah catatan pengguna, bukan audit independen terhadap jurnal.

Migrasi000005 menambah submissions, submission_events, reviewer_responses; tidak mengubah data lama. Jurnal arsip tetap dapat digunakan oleh pelacak yang sudah ada tetapi tidak untuk pelacak baru. FK jurnal restrict mencegah pelacak hilang saat jurnal dihapus. Tidak tersedia penghapusan pelacak/matriks pada modul ini; data bertahan sampai prosedur retensi terpisah ditetapkan.

## Verifikasi dan rollback

SubmissionTest memeriksa kepemilikan termasuk admin, transisi/tanggal, konflik versi, validasi respons, hubungan baris, kontinuitas jurnal arsip dan CSV. Hasil penuh dicatat bersama deployment. SQLite lokal bukan bukti konkurensi MariaDB produksi.

Rollback kode tidak menghapus tabel baru atau data pengguna. Jangan menjalankan migrate:rollback produksi untuk rollback kode rutin. Tidak ada dependensi baru.
