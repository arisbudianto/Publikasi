# Runbook produksi Publikasi

## Identitas dan preflight

Repository GitHub arisbudianto/Publikasi main; source `/home/speakver/publikasi_m00_source`; aplikasi `/home/speakver/publikasi.speakverse.id`; document root hanya public. PHP8.4 cPanel dan mysql driver MariaDB telah diverifikasi pada modul sebelumnya. Jangan overwrite `.env`, mengubah key, membaca nilai secret atau menjalankan RefreshDatabase/PHPUnit pada DB produksi.

Sebelum release: status source harus bersih; catat HEAD lama/baru dan daftar file. Bandingkan file runtime yang akan diganti terhadap checkout lama. Jika drift, berhenti dan analisis; jangan reset/clean. Buat backup scoped file existing lalu inspeksi archive. Untuk schema mutation/destructive, siapkan backup DB dan bukti restore di database terisolasi serta persetujuan tersendiri. Migrasi M08/M09 hanya menambah tabel kosong; tidak backfill atau mengganti tabel existing.

Pull --ff-only dan verifikasi SHA. Salin migration tertentu dari git archive, migrate --path=exactfile --force satu per satu. Setelah DONE, maintenance singkat, salin runtime file scoped, composer dump-autoload --no-dev --optimize, route:cache, view:cache, artisan up. Tidak ada dependency upgrade dan tidak perlu cache:clear global. Jika gagal, pulihkan file existing dari archive, pertahankan tabel additive dan keluarkan maintenance setelah runtime kompatibel. Route baru dari release gagal harus dikeluarkan dengan memulihkan routes versi lama. Jangan migrate:rollback sebagai rollback rutin.

## Pemeriksaan setelah deploy

`php artisan publikasi:health --json` dan `php artisan migrate:status --no-ansi`. Periksa HTTPS URL target, identitas Publikasi, marker modul, guest401/redirect dan bukan sekadar200. Bandingkan file terpasang dengan source. Lakukan authenticated smoke bila session pemilik tersedia; label NOT VERIFIED jika tidak tersedia, jangan buat akun/password default.

Health probe tidak menjamin backup atau provider sehat. Jika FAIL: identifikasi check spesifik; gunakan bounded log diagnosis dengan penyamaran secret/data pengguna. Jika WARN job: periksa job tertentu dan worker release; jangan retry semua failed jobs. Aplikasi modul ini tidak membuat background job baru atau scheduled task, sehingga cron/worker baru tidak diperlukan untuk flow M08/M09. Ketersediaan worker/scheduler existing belum diverifikasi.

## Backup, retensi, incident

Backup filesystem scoped pada tiap release hanya melindungi kode. Data akun, checklist, submission, respons reviewer dan ledger membutuhkan backup DB terpisah melalui cPanel Backup Wizard/backup hosting. Simpan di lokasi privat, batasi akses operator dan lakukan restore ke database terisolasi sebelum mengklaim recoverability. Jadwal harian, retensi, offsite dan pemilik backup perlu ditetapkan oleh operator; belum dikonfigurasi/diuji oleh modul ini. Jangan membawa backup ke document root atau GitHub. Jangan mencetak credentials ke CLI/log/chat.

Tidak ada penghapusan otomatis data pribadi atau ledger. Tentukan kebijakan retensi sebelum membuka pencatatan pembayaran. Log rotation, kuota disk, pemantauan HTTP eksternal dan SMTP belum diatur otomatis; pemeriksaan runtime bukan SLA. Rekonsiliasi atau refund dilakukan di kanal resmi oleh operator, tidak oleh aplikasi. Jangan mengaktifkan pembayaran sebelum kanal, instruksi, kebijakan dan prosedur ini disetujui.

Incident: catat gejala, scope pengguna, evidence release, root cause bila terbukti, recovery dan hasil smoke. Canonicalize perubahan emergency ke repository. Tulis hasil akhir di .ai/STATE.md tanpa secret.
