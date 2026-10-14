# Akun Google, verifikasi email, dan pemulihan password

## Pengaturan produksi

1. Pastikan pengirim email nyata berfungsi. `MAIL_MAILER=log` atau `array` tidak mengirim email kepada pengguna. Uji satu pesan ke alamat yang dikuasai operator, tanpa mencatat token atau isi email ke log publik. Baru kemudian set `ACCOUNT_EMAIL_ENABLED=true` dan jalankan `php artisan config:cache`. Flag ini juga mewajibkan akun lama yang belum terverifikasi untuk memverifikasi email sebelum membuka menu akun; periksa jumlah dan rencana komunikasi kepada pengguna sebelum mengaktifkannya.
2. Buat OAuth Client ID bertipe Web application di Google Cloud milik pengelola. Tambahkan URI callback persis `https://publikasi.speakverse.id/akun/google/kembali` pada Authorized redirect URIs. Set `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` hanya di `.env` runtime, lalu `php artisan config:cache`. Jika keduanya kosong, tombol Google disembunyikan dan endpoint mengembalikan 503.
3. Jalankan migrasi additive `2026_10_14_000001_add_google_sub_to_users.php` setelah backup database. Kolom `google_sub` nullable unik. Jangan mengisi otomatis dari email. Akun lama dapat masuk dengan password dan menghubungkan Google dari halaman akun. Akun admin tidak dapat masuk atau menghubungkan Google melalui alur pengguna ini.

Google memakai authorization code, state satu kali, PKCE, pertukaran kode dan userinfo melalui HTTPS server ke server. Identitas disimpan dengan `sub`, bukan email. Email Google yang sama dengan akun lokal tidak otomatis digabung. Email `@gmail.com` atau Google Workspace dengan `hd` dan `email_verified=true` dianggap terverifikasi; akun Google beralamat email pihak ketiga tetap memerlukan verifikasi lokal ketika pengiriman email aktif.

Pemulihan password memakai broker Laravel dengan token database, masa berlaku 60 menit dan throttle 60 detik. Pesan permintaan sama untuk email terdaftar atau tidak. Verifikasi email memakai signed URL Laravel. Jangan menguji kirim email atau login Google dengan akun riil tanpa otorisasi pemiliknya.
