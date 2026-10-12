# M09 — Operasional produksi dan pembayaran opsional

## Operasional

Admin membuka `/admin/operasional`. CLI: `php artisan publikasi:health --json` (exit0 bila tidak ada FAIL, exit1 bila ada FAIL). Pemeriksaan: production environment, debug off, keberadaan APP_KEY, HTTPS APP_URL, direktori aplikasi writable, koneksi DB SELECT1, keberadaan tabel, probe cache unik TTL60 dengan penghapusan, jumlah jobs/failed_jobs, kesiapan key bila AI aktif. Tidak mencetak nilai key, isi job, exception provider, email atau naskah.

WARN antrean berarti operator perlu memeriksa umur/status job; jumlah saja tidak membuktikan worker sehat. Schema check membuktikan tabel tersedia, bukan kesetaraan setiap kolom/index. Probe tidak memanggil provider AI, SMTP, gateway, scheduler, backup, atau restore. `/up` bawaan hanya liveness. `/layanan` adalah informasi publik, bukan dashboard diagnostik.

## Pembayaran opsional

Implementasi adalah ledger dukungan operasional manual, tanpa gateway, kartu, subscription, invoice pajak, webhook, charge, atau refund. Tidak ada paywall atau perubahan peringkat rekomendasi. Default OFF. Pembayaran sungguhan tidak diaktifkan dalam deployment ini karena kanal penerimaan dan penyedia belum ditentukan.

Operator dapat mengaktifkan nanti melalui lingkungan: `OPTIONAL_PAYMENTS_ENABLED=true` dan `OPTIONAL_PAYMENT_INSTRUCTIONS` berisi instruksi resmi yang telah disetujui. Keduanya harus lengkap agar permintaan dapat dibuat. Pengguna login mencatat nominal rupiah integer10.000–10.000.000, memberi acknowledgment dan hanya boleh memiliki satu permintaan pending; user row-lock menahan retry paralel. Nilai permintaan berasal dari pengguna, bukan bukti dana diterima. Setelah memeriksa kanal resmi di luar aplikasi, admin mencatat verified/declined beserta alasan20–1000 karakter dan konfirmasi. Pengguna hanya dapat membatalkan miliknya yang pending. Status terminal tidak diubah kembali. Konflik versi atau replay409. Setiap aksi menghasilkan event append-only melalui aplikasi. Verifikasi manual oleh operator tidak menggantikan rekonsiliasi bank.

Semua riwayat pemilik bersifat pribadi; admin hanya memperoleh akses ledger pembayaran, tidak pelacak submission. Tidak menyimpan data kartu, rekening lengkap atau credential. Catatan operator terlihat pemilik. Ledger baru memakai FK restrict dan tidak memiliki endpoint penghapusan. Kebijakan retensi serta refund harus ditetapkan sebelum menerima pembayaran sungguhan. Fitur tetap terbuka saat pembayaran nonaktif atau tidak terverifikasi.

## Batas kesiapan

Migration000006 additive. Tidak mengaktifkan pembayaran, mengubah .env produksi, menjalankan job, mengatur cron, menguji restore, atau mengirim dana. PaymentsTest menggunakan data sintetis dan konfigurasi sementara pada DB pengujian; tidak melakukan transaksi nyata. Gateway otomatis merupakan integrasi terpisah yang memerlukan pilihan provider, sandbox, signature, idempotency, rekonsiliasi, kebijakan biaya/refund dan pengujian sebelum aktivasi.

Lihat RUNBOOK.md untuk operasi produksi dan DEPLOYMENT_EVIDENCE.md untuk bukti aktual. Code rollback mempertahankan tabel/data baru.
