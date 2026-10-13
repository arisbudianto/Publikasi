# M13 pemeriksa referensi

## Cakupan

- Menerima paling banyak 100 referensi dan 30.000 karakter per daftar.
- Memeriksa referensi ber-DOI lewat Crossref REST API dan membandingkan judul, tahun, serta penulis pertama yang dapat dikenali dari sitasi.
- Mencari kandidat bibliografis tanpa DOI dan menandainya sebagai kemungkinan, bukan kecocokan pasti.
- Menampilkan relasi pembaruan Crossref seperti retraction, correction, dan expression of concern dengan DOI pemberitahuan.
- Mengunduh metadata yang cocok sebagai CSV atau RIS.

M13 tidak menilai apakah referensi mendukung klaim tulisan dan tidak menyebut referensi yang tidak ditemukan di Crossref sebagai palsu.

## Sumber dan privasi

Crossref REST API digunakan tanpa kunci API. Setiap referensi dikirim terpisah setelah pengguna menyetujui pengiriman. Teks referensi dan kueri pencarian tidak disimpan di database, sesi, atau log aplikasi. Cache tujuh hari hanya menyimpan bidang metadata publik yang dipakai aplikasi dan tidak menyertakan abstrak. Crossref dapat mencatat permintaan yang diterimanya; halaman memberi tahu pengguna untuk tidak mengirim daftar pustaka rahasia.

Konfigurasi `CROSSREF_MAILTO` dianjurkan untuk identifikasi permintaan yang sopan. Jika tidak diisi, aplikasi mengirim paling banyak satu permintaan per detik. Endpoint juga membatasi permintaan per IP/pengguna dan memiliki batas harian global yang dapat diubah melalui `REFERENCE_USER_DAILY_LIMIT` dan `REFERENCE_GLOBAL_DAILY_LIMIT`.

Dokumentasi sumber:

- REST API: https://www.crossref.org/documentation/retrieve-metadata/rest-api/
- Kebijakan metadata: https://www.crossref.org/documentation/retrieve-metadata/
- Penggunaan data Retraction Watch melalui Crossref: https://www.crossref.org/blog/retraction-watch-data-now-available-via-crossref-api/

## Verifikasi

- Tes fitur M13: 5 tes, 29 assertions lulus di PHP 8.3.
- Seluruh suite lokal belum menjadi verifikasi untuk baseline main karena direktori sumber lokal adalah snapshot M11/M12 dan vendor yang dipinjam berasal dari M08. GitHub Actions pada branch M13 harus menjadi gate suite penuh.
- Pengujian live terhadap Crossref dan deployment produksi dicatat setelah verifikasi terpisah.
- M13 tidak menambah migrasi database.
