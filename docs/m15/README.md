# M15 posisi riset

## Cakupan pilot

- Hanya akun terdaftar yang dapat menjalankan analisis. Batas awal global 20 analisis per bulan, 2 per akun per hari, dan 4 secara global per hari.
- Form menerima judul 10–300 karakter, abstrak 80–5.000 karakter, bidang opsional, dan rentang tahun opsional.
- Persetujuan eksplisit diperlukan sebelum judul dan abstrak dikirim ke OpenAlex serta Dinoiki. Untuk input Bahasa Indonesia, Dinoiki menerjemahkan teks; pengguna dapat menyunting terjemahan sebelum pencarian dan analisis berikutnya.
- OpenAlex menyediakan paling banyak 50 rekaman metadata. Embedding Dinoiki merangking kandidat dan halaman menampilkan paling banyak 10.
- Analisis empat dimensi (topik, variabel, metode, konteks), pembeda, risiko kemiripan, arah penguatan, dan draf klaim hanya menggunakan kandidat yang abstraknya tersedia. Kandidat tanpa abstrak tetap terlihat, tetapi tidak dikirim ke penilai dimensi.
- ID kandidat yang dikembalikan model divalidasi terhadap hasil OpenAlex. Klaim tanpa ID kandidat yang valid dibuang. Jika embedding atau model gagal, daftar dari OpenAlex tetap ditampilkan dalam mode cadangan.
- Ekspor RIS dan BibTeX dibuat di browser. Tombol PDF membuka dialog cetak browser agar pengguna dapat memilih “Simpan sebagai PDF”.
- Penyimpanan ke akun bersifat opt-in dan memakai riwayat M05 yang sudah ada. Snapshot menyimpan judul, metadata kandidat, skor, dan analisis; abstrak tidak disimpan. Riwayat dapat dihapus dari halaman akun.
- Input tidak disimpan di sesi atau log aplikasi. Tabel `m15_ai_usage` hanya mencatat total biaya/token, jumlah permintaan, dan periode.

## Biaya pilot

- Batas Dinoiki: US$5 per bulan. Sebelum setiap request AI, aplikasi mencadangkan estimasi berdasarkan panjang input, maksimum token keluaran, dan tarif konfigurasi; biaya/token provider mengoreksi agregat jika respons menyertakan pemakaian. Jika provider tidak menyertakannya, ledger mencatat biaya cadangan secara konservatif. Perkiraan memakai harga konfigurasi GPT-5 mini dan text-embedding-3-small yang diperiksa pada 13 Oktober 2026. Tinjau tarif provider sebelum memperpanjang pilot.
- Maksimum 20 analisis per bulan juga membatasi pilot manusia dan pembelanjaan.
- OpenAlex memakai kuota gratis harian yang tersedia dan biaya `meta.cost_usd` direkam terpisah untuk pengukuran. Batas harian aplikasi jauh di bawah kuota gratis; kunci API opsional dapat diatur melalui `OPENALEX_API_KEY` dan dikirim sebagai bearer token.
- Pengukuran biaya adalah metrik penggunaan API, bukan tagihan final provider. Cocokkan dengan dashboard Dinoiki dan OpenAlex sebelum menaikkan kuota. Retensi input di pihak penyedia belum diaudit.

## Operasi dan privasi

- M15 tidak mengambil PDF atau teks lengkap; hasil berasal dari metadata OpenAlex dan abstrak yang tersedia.
- Input dan abstrak kandidat diperlakukan sebagai data eksternal tak tepercaya. Blade meng-escape keluaran, dan instruksi model melarang mengikuti perintah yang tertanam dalam metadata.
- Response diberi `Cache-Control: no-store, private` dan `X-Robots-Tag: noindex, noarchive`.
- Migrasi `2026_10_13_000002_create_m15_ai_usage_table` menambah satu tabel agregat. Tidak ada perubahan tabel katalog, akun, atau transaksi.
- Sebelum migrasi produksi, buat dan verifikasi backup database. Rollback kode tidak perlu menghapus tabel pemakaian; migrasi `down` menghapus agregat biaya dan harus dijalankan hanya jika pemilik memilih kehilangan data tersebut.

## Sumber dan batasan

- OpenAlex API dan metadata: https://docs.openalex.org/ dan https://help.openalex.org/api/authentication/ . OpenAlex menyatakan metadata CC0, API memiliki kuota gratis harian dan harga per operasi.
- Dinoiki API: https://dinoiki.com/ai . Endpoint OpenAI-compatible mencakup `/v1/chat/completions` dan `/v1/embeddings`; tarif model dapat berubah.
- Skor embedding hanya mengurutkan kandidat yang ditemukan. Skor bukan persentase kebaruan, plagiarisme, atau peluang penerimaan.
- OpenAlex tidak mencakup semua publikasi; abstrak tidak selalu tersedia. Model dapat membuat interpretasi keliru meskipun ID sitasi valid. Pemeriksaan literatur oleh penulis tetap diperlukan.
- Kualitas relevansi, biaya 20 kasus pilot, retensi provider, perilaku penuh pada hosting, dan evaluasi manusia belum dianggap tervalidasi sampai hasil produksi dan pilot dicatat.
