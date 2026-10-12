# M06 Pemutakhiran dan verifikasi data

Admin /admin/kurasi menyediakan daftar semua jurnal, data perlu diperiksa (>180 hari), dan klaim penerbit belum diverifikasi. Scope tetap diperbarui melalui editor M01 dengan audit before/after. Peringatan scope lama tampil pada detail, perbandingan dan hasil pencocokan. Tanggal pemeriksaan bukan masa berlaku.

Admin menerbitkan observasi indeksasi/biaya/waktu dengan label, nilai beserta satuan, periode/kategori, sumber HTTPS, tanggal tidak di masa depan, status publisher_claim/verified, catatan bukti minimal20 karakter serta konfirmasi pemeriksaan. Verifikasi manual dari sumber utama oleh admin, bukan hasil HTTP200/AI/akreditasi otomatis. Tidak ada scraping Scopus/WoS/SINTA atau cron eksternal.

Label+jenis sama menggantikan observasi aktif dalam transaksi dengan lock jurnal. Versi lama ditandai retired_at, tidak dihapus. Riwayat admin20/halaman dan audit30 terbaru menampilkan perubahan. Tarik observasi memerlukan alasan dan mempertahankan versi/audit. Semua write admin-only, relasi journal/observation diperiksa. Publik hanya mendapat observasi aktif; M02 known/unknown dan perbandingan mengikuti versi aktif. Catatan bukti internal/admin ID tidak dipublikasikan.

Admin dapat memuat ulang sampel artikel Crossref pada jurnal tertentu: invalidasi satu cache ISSN, fetch metadata3 artikel, audit status/count/source/date. Tidak mengirim naskah/API AI, tidak mengubah status verifikasi indeksasi. Sumber gagal ditampilkan sebagai kegagalan, tidak diterbitkan sebagai fakta terverifikasi. Scope embedding M04 menggunakan hash isi sehingga perubahan scope otomatis memakai cache baru.

Migration000003 additive nullable retired_at/reviewed_by/review_note; FK reviewer users nullOnDelete. Observasi lama tetap aktif dengan reviewer/catatan belum tersedia. Existing data tidak diubah/backfill. Rollback code mempertahankan kolom dan riwayat; jangan drop kolom yang telah berisi data tanpa approval destructive.

TESTED PHP8.3:46 tests/307 assertions PASS: admin boundaries, version preservation, latest-only public/detail/compare/filter, retirement audit, invalid URL/date/confirmation/note, wrong-journal IDOR, stale dashboard/public badge, scoped Crossref refresh/audit; regresi M01-M05.
NOT VERIFIED predeploy: real admin browser writes; scheduled external synchronization not implemented (manual official-source curation deliberately retained).
