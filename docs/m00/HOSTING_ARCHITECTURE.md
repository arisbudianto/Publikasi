# M00 Arsitektur dan kelayakan hosting
## Rekomendasi
Keputusan pengguna 2026-10-11: Laravel 13 dan MySQL.
Satu aplikasi modular Laravel 13 + Blade + MySQL; pencocokan aturan berjalan lokal terhadap katalog.
Tidak memerlukan Redis, server vektor, worker permanen atau AI API untuk MVP.
Frontend assets dibangun di CI/lokal lalu dikirim ke hosting; Node di cPanel bukan persyaratan runtime.

Laravel 13 memerlukan PHP >=8.3 beserta extension resmi. Framework dan database sudah ditetapkan pengguna; pemeriksaan hosting memastikan kompatibilitas, bukan memilih framework atau mengganti MySQL dengan MariaDB.
Sumber diperiksa 2026-10-11:
https://laravel.com/framework/docs/13.x/deployment
Dokumentasi menekankan entrypoint public/index.php, directory writable storage dan bootstrap/cache, serta debug produksi false.

## Modul aplikasi
Catalog -> Query/Filter -> MatchingRules -> Comparison.
AdminCurator mengelola Catalog + Evidence + Audit.
Adapter enrichment terpisah; pencarian publik tidak memanggil sumber eksternal langsung pada MVP.
Controller validasi request dan otorisasi; aturan bisnis berada di service; data observations terpisah dari tampilan.
Database MySQL dengan utf8mb4. Versi, SQL mode dan dukungan index harus diuji sebelum migration.

## Pemeriksaan hosting aktual
Tidak ada sesi cPanel/SSH atau output runtime untuk aplikasi ini dalam sesi M00.
Akses public domain tidak memberi bukti PHP CLI, Composer, database, cron, atau document root.
Percobaan membaca https://publikasi.speakverse.id melalui web lookup tidak berhasil. Ini bukan bukti domain tidak aktif.
Status keseluruhan: UNKNOWN, belum GO.

| Check | Kebutuhan/kriteria | Bukti yang perlu dicatat | Status |
|---|---|---|---|
| PHP web dan CLI | Kompatibel Laravel 13, PHP >=8.3 | Versi PHP kedua konteks | UNKNOWN |
| Extensions | Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML; pdo_mysql | Daftar extension web/CLI | UNKNOWN |
| Database | MySQL kompatibel, utf8mb4, create/migrate/index | Versi, test DB terisolasi | UNKNOWN |
| Document root | Hanya direktori public terlayani | Pengaturan subdomain dan uji file nonpublic | UNKNOWN |
| Rewrite | Routing front controller berfungsi | Uji route non-root pada staging | UNKNOWN |
| HTTPS | Sertifikat valid dan renewal tersedia | Uji TLS domain | UNKNOWN |
| Storage permissions | storage/bootstrap/cache writable terbatas | Uji permission staging | UNKNOWN |
| Build/deploy | Composer di hosting atau vendor dibangun di lingkungan kompatibel | Metode release yang dipilih | UNKNOWN |
| Shell/Artisan | SSH/Terminal atau jalur operasi setara yang aman | Kemampuan menjalankan Artisan | UNKNOWN |
| Cron | Tidak blocking katalog; perlu untuk jadwal tahap lanjutan | Cron test bila dipakai | UNKNOWN |
| Outbound HTTPS | Tidak blocking MVP offline; perlu enrichment/AI | Uji endpoint bila integrasi dipilih | UNKNOWN |
| Limits | Memory, execution, disk/inode sesuai dataset | Nilai cPanel dan pengukuran pilot | UNKNOWN |
| Backup | Backup database/files dan restore terisolasi | Hasil uji restore | UNKNOWN |
| SMTP | Tidak diperlukan akses guest/admin awal | Uji bila akun recovery/email dipilih | DEFERRED |

## Cara mengumpulkan bukti
Pemilik dapat memberikan screenshot pengaturan cPanel yang sudah disamarkan atau akses melalui mekanisme aman.
Jangan kirim password/token/.env ke chat atau repository.
Di Terminal cPanel, pemeriksaan read-only yang relevan: php -v, php -m, composer --version bila tersedia.
Versi PHP web diperiksa lewat panel pengaturan PHP atau mekanisme hosting yang aman, bukan membuka phpinfo publik.
Catat versi DB dari panel/phpMyAdmin, document root subdomain, batas resource, cron dan backup.
Pemeriksaan staging yang membuat file/database baru dilakukan pada lingkungan terisolasi saat protokol development/operasi dimuat; belum dijalankan M00.

## Decision tree
- GO: runtime sesuai, public root aman, DB/permissions benar, jalur deployment tersedia, backup dan restore terbukti sebelum rilis.
- CONDITIONAL GO: belum ada Composer/Node di server tetapi artefak kompatibel dapat dibangun di luar; cron/SMTP belum ada namun fitur terkait ditunda.
- NO-GO: runtime tak kompatibel, source/secret terlayani publik, tidak ada jalur DB/deploy aman.
- UNKNOWN: bukti belum tersedia. Jangan mengubah UNKNOWN menjadi PASS.

## Privasi dan operasional rancangan
MVP memproses abstrak sementara; exclude request body dari log/error telemetry dan tidak membuat SearchSession persisten.
HTTPS tetap wajib; validasi input, CSRF admin, rate limit login/pencocokan, output escaping.
Secret hanya konfigurasi server; admin password di-hash; tidak membuat kredensial default publik.
Backup sebelum deployment/migration dan restore di lingkungan terisolasi, bukan menimpa database SpeakVerse.
Database dan APP_KEY Publikasi terpisah dari SpeakVerse.
Target performa usulan: katalog/filter p95 <=2 detik dan rules matching p95 <=3 detik pada 50 jurnal, 10 user simultan di staging. Target diuji, bukan klaim hasil.
