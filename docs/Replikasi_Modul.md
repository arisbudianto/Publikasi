# Replikasi Modul: CekJurnal.id dan Novelty Lab ke Publikasi
Tanggal: 12 Oktober 2026
Mode: APOPS BROWNFIELD (lanjutan Rencana Modul 11 Oktober 2026)
Repository: https://github.com/arisbudianto/Publikasi.git
Deployment: publikasi.speakverse.id melalui cPanel
Basis prototipe: rilis df62fad (M08/M09) sesuai .ai/STATE.md
Status: blueprint yang direalisasikan bertahap; status rilis tercatat di `.ai/STATE.md`.

## 1. Tujuan dan batas replikasi
Melengkapi prototipe Publikasi dengan fungsi yang terbukti dipakai di dua aplikasi referensi:
- CekJurnal.id (Perpustakaan Polines, gratis): memilih jurnal tujuan dan memeriksa integritasnya sebelum submit.
- Novelty Lab (berbayar Rp94.500): memetakan posisi kebaruan ide riset terhadap publikasi terdekat sebelum naskah ditulis jauh.

Yang direplikasi adalah fungsi dan alur. Merek, tampilan, teks, dan database kedua aplikasi tidak disalin.

Yang sengaja tidak direplikasi:
- Data turunan milik CekJurnal (2.390 jurnal SINTA, 369 free APC, 7.178 predator, 1.172 discontinued). Angka itu klaim mereka, bukan target Publikasi.
- Taktik penjualan Novelty Lab: penghitung kuota "423 dari 500", "200+ peneliti bulan ini", testimoni, dan contoh hasil berdata ilustrasi.
- Paket "analisis tanpa batas, akses selamanya". Aturan M09 tetap berlaku: biaya AI diukur sebelum menawarkan akses tanpa batas atau lifetime.
- Persentase kemiripan yang dibaca sebagai tingkat plagiarisme atau peluang diterima.

Observasi referensi yang dipakai rancangan ini (diperiksa 12 Oktober 2026):
- CekJurnal: direktori SINTA dengan filter peringkat, APC, bidang, bahasa, frekuensi terbit; halaman free APC; Jurnal Match AI (abstrak minimal 100 karakter, versi AI dan versi klasik, cakupan SINTA); chatbot Ceki; daftar predator; alat evaluasi jurnal berbasis kriteria dan skor; alat penentu authorship; cek referensi (sitasi ada dan belum ditarik); daftar discontinued Scopus; pusat literasi; kelas tatap muka; urun daya koreksi data.
- Novelty Lab: input judul dan abstrak (bahasa Indonesia diterjemahkan dulu); sumber OpenAlex, Crossref, Semantic Scholar; output publikasi terdekat dengan kemiripan, overlap empat dimensi (topik, variabel, metode, konteks), pembeda terkuat, risiko kemiripan, arah penguatan, draf klaim kebaruan; ekspor PDF, RIS, BibTeX; riwayat akun; add-on pemantauan publikasi baru, perbandingan versi ide, dan paket research gap/novelty/contribution statement.
- Temuan yang memengaruhi desain: artikel CekJurnal 30 September 2026 menyatakan peringkat SINTA berubah dari enam menjadi empat, sementara beranda dan filter mereka masih menulis 1 sampai 6. Publikasi wajib menyimpan peringkat sebagai observasi bersumber dengan skema dan periode, bukan angka tetap 1 sampai 6. Perubahan aturan ini perlu diverifikasi ke sumber resmi sebelum dipakai.

## 2. Peta fitur referensi terhadap prototipe
| Fungsi referensi | Asal | Kondisi prototipe saat ini | Modul |
|---|---|---|---|
| Direktori jurnal dengan pencarian | CekJurnal | Ada (M01), 5 jurnal produksi, satu kolom ISSN | M10 |
| Filter peringkat SINTA, APC, bahasa, frekuensi | CekJurnal | Belum. Observasi masih teks bebas; filter rentang ditunda di M02 | M10, M11 |
| Halaman jurnal free APC | CekJurnal | Belum. Aturan "unknown bukan gratis" sudah ada | M11 |
| Rekomendasi jurnal dari abstrak, versi klasik dan AI | CekJurnal | Ada (M03 kata kunci, M04 embedding). AI dibatasi 50 jurnal, top 3 | M10 (skala) |
| Daftar discontinued Scopus | CekJurnal | Belum | M12 |
| Daftar jurnal predator | CekJurnal | Belum | M12 (keputusan D11) |
| Alat evaluasi jurnal berbasis kriteria | CekJurnal | Belum | M12 |
| Cek referensi (ada, DOI benar, ditarik) | CekJurnal | Belum. Integrasi Crossref sudah ada untuk sampel artikel | M13 |
| Alat penentu authorship | CekJurnal | Belum | M14 |
| Chatbot tanya jurnal | CekJurnal | Belum | M17 |
| Pusat literasi dan urun daya koreksi | CekJurnal | Belum. Antrean kurasi admin sudah ada (M06) | M18 |
| Publikasi terdekat dari ide riset | Novelty Lab | Pilot M15 memakai OpenAlex | M15 |
| Overlap empat dimensi, pembeda, risiko, arah penguatan | Novelty Lab | Pilot M15 memakai kandidat berabstrak dan validasi ID sumber | M15 |
| Draf klaim kebaruan | Novelty Lab | Pilot M15, tetap harus ditinjau penulis | M15 |
| Terjemahan abstrak Indonesia ke Inggris | Novelty Lab | Pilot M15 dengan persetujuan dan suntingan sebelum pencarian | M15 |
| Ekspor RIS, BibTeX, PDF | Novelty Lab | Pilot M15: RIS/BibTeX lokal, PDF melalui cetak browser | M15 |
| Riwayat hasil di akun | Novelty Lab | Pilot M15 opt-in, abstrak tidak disimpan | M15 |
| Pemantauan publikasi baru dan perbandingan versi ide | Novelty Lab | Belum | M16 |
| Checklist submission, pelacak, matriks reviewer | Tidak ada di keduanya | Ada (M07, M08) | Tetap |

Posisi produk setelah replikasi: satu alur dari ide sampai submission. Cek posisi riset (M15), cocokkan jurnal (M03/M04), periksa jurnal (M12), cek referensi (M13), checklist (M07), lacak submission (M08). CekJurnal berhenti di pemilihan jurnal, Novelty Lab berhenti di kebaruan; tidak ada yang menyambungkan keduanya.

## 3. Urutan modul baru
| Modul | Tingkat | Hasil yang bisa digunakan | Dependensi |
|---|---|---|---|
| M10 Observasi terstruktur dan skala katalog | Menengah | Data peringkat, biaya, bahasa, frekuensi dapat difilter; katalog bisa ratusan sampai ribuan jurnal | M01, M06, keputusan D10 |
| M11 Direktori dan jurnal tanpa APC | Sederhana | Filter dan halaman jurnal tanpa biaya publikasi | M10 |
| M12 Integritas jurnal | Menengah | Status discontinued bersumber dan evaluasi kriteria sebelum submit | M10, sumber resmi, D11 |
| M13 Cek referensi | Menengah | Daftar pustaka diperiksa keberadaan, DOI, dan status retraksi | Crossref (sudah dipakai M04) |
| M14 Penentu authorship | Sederhana | Rekomendasi penulis vs ucapan terima kasih dan catatan urutan | Tidak ada |
| M15 Posisi riset | Kompleks | Publikasi terdekat, overlap empat dimensi, pembeda, draf klaim | M04, M05, D12 sampai D14 |
| M16 Pemantauan topik dan versi ide | Kompleks | Pemberitahuan publikasi baru, perbandingan versi | M15, cron (D16) |
| M17 Asisten tanya | Menengah | Jawaban pertanyaan dari katalog dan literasi sendiri | M10, M18, D12 |
| M18 Literasi dan urun daya | Sederhana | Artikel panduan dan usulan koreksi masuk antrean kurasi | M06 |

Urutan pengerjaan yang disarankan: M10, M11, lalu alat tanpa AI (M13, M14, M12), baru M15. Alasannya: M10 menjadi prasyarat filter dan skala; M13 dan M14 murah dan langsung berguna; M15 paling mahal dan paling berisiko klaim.

## 4. Desain informasi dan navigasi
Navigasi utama saat ini: Katalog, Cocokkan naskah, Pencocokan AI, Akun, Kelola katalog. Usulan setelah replikasi:

| Menu | Isi | Route usulan |
|---|---|---|
| Cari jurnal | Katalog, filter, tanpa APC, bandingkan | /, /tanpa-apc, /bandingkan |
| Cocokkan naskah | Satu halaman, pilihan mode dasar atau AI | /cocokkan, /cocokkan-ai (tetap) |
| Posisi riset | Analisis kebaruan dan riwayat | /posisi-riset |
| Periksa | Evaluasi jurnal, discontinued, cek referensi, authorship | /periksa/jurnal, /periksa/discontinued, /periksa/referensi, /periksa/authorship |
| Panduan | Literasi dan usulan koreksi | /panduan, /usulkan |
| Akun | Shortlist, riwayat, checklist, submission | /akun (tetap) |

Prinsip tampilan untuk semua modul baru:
- Setiap fakta jurnal tampil bersama sumber, tanggal periksa, dan status verifikasi (publisher_claim atau verified), mengikuti pola M01 sampai M06.
- Nilai tidak diketahui ditulis "belum diketahui", tidak dikosongkan dan tidak dianggap nol.
- Hasil AI diberi label mode (dasar, AI, cadangan) dan batasnya ditulis di bawah hasil, bukan di halaman terpisah.
- Halaman tetap berfungsi tanpa JavaScript untuk alur inti; JavaScript hanya untuk kenyamanan (salin, buka-tutup rincian).
- Lebar ponsel: tabel bisa digeser horizontal, tombol aksi utama di bawah konten.

## 5. Detail modul dan kriteria selesai

### M10 Observasi terstruktur dan skala katalog
Tujuan: membuat data yang bisa difilter tanpa mengarang angka dari teks.
Masalah prototipe: journal_observations.value berupa string 255 karakter; ISSN hanya satu kolom unik; SemanticMatcher memuat seluruh katalog per request dan menolak lebih dari 50 jurnal.
Pekerjaan:
- Tambah kolom terstruktur pada observasi secara aditif: metric_scheme (misal SINTA-2026, SINTA-lama, Scopus-SJR), rank_value, amount_minor, currency, fee_route (APC OA, subscription, submission fee, lainnya), language, frequency_per_year, effective_from, effective_to. Kolom value lama tetap untuk tampilan dan riwayat.
- Tabel journal_identifiers untuk ISSN cetak, ISSN elektronik, ISSN-L. Kolom issn lama dipertahankan sebagai identitas utama sampai migrasi data selesai.
- Impor CSV observasi dengan preview dan commit transaksional, mengikuti pola impor M01 (batas baris, laporan per baris, tanpa commit parsial).
- Vektor scope disimpan di database (tabel journal_embeddings: journal_id, model, scope_hash, vector biner) dan diperbarui saat scope berubah. Pencocokan AI membaca vektor tersimpan, sehingga batas 50 jurnal bisa dinaikkan tanpa embedding ulang per request.
Desain layar:
- Admin, form observasi: pilih jenis, lalu field yang relevan muncul (peringkat: skema, nilai, periode; biaya: jumlah, mata uang, jalur, waiver). Sumber dan catatan bukti tetap wajib seperti M06.
- Admin, impor observasi: unggah CSV, tabel preview dengan kolom status per baris (valid, konflik, ditolak beserta alasan), tombol terapkan hanya aktif bila tidak ada baris ditolak.
Kriteria selesai:
- Observasi lama tetap tampil sama; tidak ada backfill yang menebak angka dari teks value.
- Peringkat SINTA menyimpan skema dan periode; filter tidak mencampur skema lama dan baru tanpa label.
- Biaya Rp0 hanya tersimpan bila sumber menyatakan nol secara eksplisit.
- Dua ISSN untuk jurnal yang sama tidak membuat dua jurnal; ISSN yang sudah dipakai jurnal lain ditolak dengan pesan.
- Pencocokan AI pada 500 jurnal uji selesai dalam batas waktu yang disepakati di D16 dan tidak memanggil embedding untuk scope yang hash-nya tidak berubah.
- Uji regresi M01 sampai M09 tetap lulus.
Di luar modul: scraping SINTA, Scopus, atau situs penerbit; pengisian massal tanpa review.

### M11 Direktori dan jurnal tanpa APC
Tujuan: menyamai fungsi utama direktori CekJurnal dengan data yang bisa ditelusuri sumbernya.
Fitur:
- Filter bidang, skema dan nilai peringkat, indeksasi, jalur biaya, APC maksimum, bahasa, frekuensi terbit.
- Urutan: nama, peringkat, biaya terendah, tanggal periksa terbaru.
- Halaman /tanpa-apc: jurnal dengan observasi biaya nol terverifikasi pada jalur yang jelas, plus daftar terpisah "biaya belum diketahui" yang tidak dicampur.
Desain layar:
- Desktop: panel filter di kiri, daftar di kanan. Ponsel: tombol "Filter (n aktif)" membuka panel di atas daftar.
- Kartu jurnal: nama, penerbit, ISSN, bidang, chip peringkat dengan skema dan periode, biaya dengan jalur dan mata uang asli, tanggal periksa, badge "perlu diperiksa" bila lewat interval M06, tombol bandingkan dan simpan.
- Hasil kosong: daftar filter aktif dengan tautan melepas masing-masing.
Kriteria selesai:
- Biaya belum diketahui tidak lolos filter tanpa APC maupun APC maksimum.
- Jurnal dengan klaim penerbit dan jurnal terverifikasi dapat dibedakan di kartu dan di filter.
- Kombinasi filter tersimpan di URL dan bertahan melewati pagination dan perbandingan.
- Halaman tanpa APC menyebut tanggal data dan mengingatkan memeriksa halaman biaya resmi sebelum submit.

### M12 Integritas jurnal
Tujuan: membantu pengguna menghindari jurnal bermasalah dengan bukti, bukan label.
Fitur:
- Status discontinued Scopus dari daftar resmi Elsevier: tanggal penghentian, alasan bila tersedia, sumber, tanggal impor. Dicocokkan lewat ISSN. Jurnal yang tidak ada di katalog tetap bisa dicari pada halaman /periksa/discontinued.
- Alat evaluasi jurnal: kuesioner kriteria terdokumentasi (identitas dan ISSN valid, editorial board dapat diverifikasi, proses peer review dijelaskan, biaya diumumkan sebelum submit, klaim indeksasi dapat dicek, janji waktu terbit tidak wajar, email undangan massal, dan seterusnya). Setiap kriteria punya bobot, penjelasan, dan cara memeriksa.
- Peringatan di detail jurnal dan hasil pencocokan bila ada status discontinued aktif.
Desain layar:
- /periksa/jurnal: kolom ISSN atau nama. Bila jurnal ada di katalog, data bersumber tampil di atas kuesioner. Kuesioner satu halaman dengan pilihan ya, tidak, tidak tahu per kriteria. Hasil: skor, daftar kriteria yang memicu kekhawatiran, dan langkah verifikasi berikutnya.
- Hasil memakai tiga kategori (tidak ditemukan masalah pada kriteria yang dijawab, perlu diperiksa lebih lanjut, banyak tanda bahaya), bukan vonis "predator".
Kriteria selesai:
- Status discontinued hanya tampil bila ada sumber resmi dan periode; artikel sebelum tanggal penghentian tidak disebut tidak terindeks.
- Jawaban "tidak tahu" tidak dihitung sebagai aman.
- Skor dan kategori deterministik untuk jawaban yang sama; versi kriteria dicatat.
- Kuesioner tidak menyimpan apa pun untuk tamu; pengguna login boleh menyimpan secara opt-in.
Keputusan terbuka D11 untuk daftar predator: lihat bagian 8. Sampai diputuskan, modul ini tidak menerbitkan daftar nama jurnal atau penerbit berlabel predator.

Realisasi M12: checklist versi `2026-10-v1` memakai tiga kategori tanpa vonis, tamu tidak menyimpan jawaban, akun menyimpan hanya setelah opt-in, dan pengguna dapat menghapus hasil miliknya. Daftar discontinued memakai snapshot CSV resmi Elsevier yang diimpor operator; kode menyediakan validasi ISSN, preview/checksum, dan impor snapshot bertanggal. Tidak ada daftar atau data contoh yang disertakan. Sebelum snapshot resmi pertama diimpor, halaman discontinued tetap menjelaskan bahwa belum ada data lokal.

### M13 Cek referensi
Tujuan: mendeteksi sitasi yang tidak ada, DOI yang salah, dan artikel yang ditarik, termasuk daftar pustaka hasil AI.
Input: tempel daftar pustaka, maksimal 100 entri dan 30.000 karakter, format APA atau bebas.
Pendekatan:
- Pisahkan entri per baris atau nomor; ambil DOI bila ada.
- Entri dengan DOI: ambil metadata Crossref, bandingkan judul, tahun, dan penulis pertama.
- Entri tanpa DOI: pencarian bibliografis Crossref, ambil kandidat teratas, tampilkan sebagai kemungkinan cocok dengan tingkat kemiripan judul, bukan kepastian.
- Status retraksi, koreksi, dan expression of concern dari metadata pembaruan Crossref, termasuk data Retraction Watch yang tersedia melalui Crossref.
- Persetujuan eksplisit sebelum referensi dikirim satu per satu ke Crossref. Teks sitasi dan kueri tidak disimpan; cache hanya metadata publik tanpa abstrak.
Desain layar:
- Satu kolom teks besar, tombol periksa. Hasil berupa tabel: nomor entri, teks asli dipotong, status, metadata yang ditemukan dengan tautan DOI, catatan perbedaan.
- Status: cocok, cocok dengan perbedaan (sebut field yang beda), kemungkinan cocok, tidak ditemukan, gagal diperiksa. Pemberitahuan retraksi, koreksi, atau expression of concern ditampilkan terpisah dengan tautan DOI.
- Ringkasan di atas tabel: jumlah per status. Ekspor CSV dan RIS untuk entri yang cocok.
Kriteria selesai:
- "Tidak ditemukan" tidak disebut palsu; teksnya menjelaskan bahwa tidak semua publikasi terdaftar di Crossref (buku, prosiding lokal, jurnal tanpa DOI).
- Status ditarik hanya tampil dari metadata sumber dengan tautan pemberitahuannya.
- Daftar pustaka tidak disimpan ke database atau log; cache hanya metadata DOI publik.
- Kegagalan atau timeout Crossref per entri tidak menggagalkan seluruh hasil.
- Throttle per pengguna dan batas harian global, mengikuti pola M04.
- Tidak ada migrasi database. Detail implementasi dan sumber: `docs/m13/README.md`.
Di luar modul: memeriksa kesesuaian isi sitasi dengan klaim di naskah.

### M14 Penentu authorship
Tujuan: membantu tim memutuskan siapa layak menjadi penulis berdasarkan kriteria yang diakui.
Fitur:
- Kriteria ICMJE empat syarat dan peran CRediT 14 kategori per orang.
- Output per orang: memenuhi syarat penulis, belum memenuhi (sebut syarat yang kurang), atau cocok di ucapan terima kasih.
- Usulan urutan berdasarkan kontribusi tercatat, dengan catatan bahwa kebiasaan urutan berbeda per bidang dan jurnal.
- Teks pernyataan kontribusi CRediT siap salin.
Desain layar:
- Tabel isian: baris per orang (nama atau inisial), kolom peran CRediT sebagai centang, kolom empat syarat ICMJE. Tambah baris maksimal 30.
- Hasil di bawah tabel tanpa pindah halaman; tombol salin pernyataan kontribusi.
Kriteria selesai:
- Aturan dan sumber kriteria ditulis di halaman, dengan versi.
- Orang dengan peran pendanaan atau supervisi saja tidak otomatis menjadi penulis.
- Tidak ada data tersimpan untuk tamu; tidak memakai AI.
- Teks hasil menyatakan bahwa keputusan akhir mengikuti kebijakan jurnal dan institusi.

Realisasi M14: halaman `/periksa/authorship` menerapkan versi aturan `2026-10-v1`, memproses maksimal 30 kontributor tanpa penyimpanan, memberi usulan urutan sementara berdasarkan jumlah peran CRediT tanpa pembobotan, serta membuat draf pernyataan kontribusi yang bisa disalin. Sumber aturan ICMJE dan CRediT serta batasan implementasi tercatat di `docs/m14/README.md`.

### M15 Posisi riset
Tujuan: menunjukkan publikasi terdekat dan letak pembeda ide riset, dengan setiap pembanding dapat dibuka.
Input: judul (10 sampai 300 karakter), ringkasan atau abstrak (80 sampai 5.000), bidang opsional, rentang tahun opsional, persetujuan pemrosesan pihak ketiga (wajib, seperti M04).
Pendekatan:
1. Bila input berbahasa Indonesia, terjemahkan ke bahasa Inggris akademik. Terjemahan ditampilkan dan bisa disunting pengguna sebelum pencarian dilanjutkan.
2. Ambil kandidat dari OpenAlex (pencarian judul dan abstrak), dilengkapi Crossref bila perlu. Semantic Scholar opsional menunggu D14. Maksimal 50 kandidat dengan judul, abstrak bila tersedia, tahun, venue, DOI atau ID sumber.
3. Rangking kandidat dengan embedding terhadap input; ambil 10 terdekat.
4. Model bahasa menilai overlap empat dimensi (topik, variabel, metode, konteks) hanya dari teks kandidat yang diambil. Setiap pernyataan merujuk ID kandidat. Output divalidasi: ID yang tidak ada di kandidat dibuang.
5. Susun pembeda terkuat, risiko kemiripan, arah penguatan, dan draf klaim kebaruan yang menyebut pembanding.
Desain layar:
- Langkah 1, form: judul, abstrak, bidang, rentang tahun, centang persetujuan. Tombol "Periksa posisi".
- Langkah 2, konfirmasi terjemahan (hanya bila input Indonesia): teks asli dan terjemahan berdampingan, bisa disunting, tombol lanjut.
- Langkah 3, hasil dengan urutan blok:
  a. Ringkasan: jumlah kandidat dianalisis, sumber dan tanggal pencarian, mode.
  b. Publikasi terdekat: daftar 10 dengan skor kedekatan semantik, tahun, venue, tautan DOI, alasan kesamaan dan perbedaan per item.
  c. Overlap empat dimensi: per dimensi tingkat rendah, sedang, tinggi beserta publikasi yang menyebabkannya.
  d. Pembeda terkuat dan risiko kemiripan, masing-masing dengan rujukan publikasi.
  e. Arah penguatan.
  f. Draf klaim kebaruan, ditandai draf, tombol salin.
  g. Batasan: cakupan sumber metadata, abstrak yang tidak tersedia, bahasa.
  h. Aksi lanjut: ekspor RIS, BibTeX, PDF; simpan ke akun (opt-in); "Cocokkan ke jurnal" yang membawa judul dan abstrak ke M04 tanpa mengetik ulang.
Kriteria selesai:
- Semua publikasi yang tampil berasal dari respons sumber metadata dan punya tautan yang bisa dibuka; tidak ada referensi buatan model.
- Kandidat tanpa abstrak diberi tanda dan tidak dipakai untuk menilai dimensi metode atau variabel.
- Skor ditulis sebagai kedekatan semantik terhadap kandidat yang ditemukan, bukan persentase kebaruan, plagiarisme, atau peluang diterima. Tidak ada skor tunggal "indikator posisi 72/100" tanpa rincian sumbernya.
- Hasil kosong atau kandidat sedikit dijelaskan sebagai keterbatasan pencarian, bukan bukti ide baru.
- Bila model bahasa gagal atau timeout, hasil tetap menampilkan publikasi terdekat dari langkah 3 dengan label mode cadangan.
- Abstrak tidak disimpan kecuali pengguna memilih menyimpan; log tidak memuat input.
- Biaya per analisis diukur pada 20 kasus pilot dan dicatat sebelum fitur dibuka publik; batas harian dan throttle diterapkan.
- Evaluasi manusia pada kasus pilot: peninjau menilai apakah publikasi terdekat relevan dan apakah klaim pembeda didukung rujukannya. Ambang kelulusan dikunci sebelum build.
Dependensi: Dinoiki chat completions dan embeddings (D12), akses OpenAlex (D13), Semantic Scholar opsional ditunda (D14). Paket "siap tulis" tidak dipisah sebagai add-on. Implementasi pilot dan batasannya dicatat di `docs/m15/README.md`.

### M16 Pemantauan topik dan versi ide
Tujuan: memberi tahu pengguna bila muncul publikasi baru yang dekat dengan ide tersimpan.
Fitur:
- Pengguna menyimpan analisis M15 sebagai topik pantau (opt-in, menyimpan teks input terenkripsi atau versi ringkas sesuai pilihan).
- Pemeriksaan berkala ke sumber metadata; publikasi baru yang melewati ambang kedekatan dicatat sebagai temuan.
- Perbandingan dua versi ide: publikasi terdekat yang bertambah, hilang, dan perubahan overlap dimensi.
Desain layar:
- Akun, tab Pantauan: daftar topik, tanggal pemeriksaan terakhir, jumlah temuan baru, tombol hentikan pantau.
- Halaman versi: dua kolom versi lama dan baru, daftar perubahan publikasi terdekat.
Kriteria selesai:
- Pemeriksaan berjalan lewat cron yang terbukti aktif di hosting; tanpa cron, fitur tidak ditawarkan.
- Pemberitahuan tampil di akun walau email belum tersedia.
- Pengguna dapat menghapus topik pantau beserta teks tersimpannya.
- Biaya pemeriksaan berkala dibatasi per pengguna dan tercatat.

### M17 Asisten tanya
Tujuan: menjawab pertanyaan umum tentang jurnal, biaya publikasi, dan integritas dari data Publikasi sendiri.
Fitur: tanya jawab yang hanya mengambil jawaban dari katalog published, observasi bersumber, dan artikel panduan M18; setiap jawaban menyertakan tautan sumber internal.
Desain layar: kolom pertanyaan di halaman Panduan dan di detail jurnal; jawaban dengan daftar sumber di bawahnya; tombol "jawaban ini keliru" yang masuk antrean M18.
Kriteria selesai:
- Pertanyaan di luar data dijawab dengan "tidak tersedia di data Publikasi", bukan dengan pengetahuan umum model.
- Tidak menyatakan jurnal predator, aman, atau pasti diterima.
- Percakapan tidak disimpan untuk tamu.
Prioritas rendah: fungsi ini mengulang apa yang sudah bisa dicari di katalog dan panduan.

### M18 Literasi dan urun daya
Tujuan: menambah pengetahuan pengguna dan jalur koreksi data dari luar admin.
Fitur:
- Artikel panduan sederhana yang ditulis admin: APC, peringkat, discontinued, cara membaca hasil Publikasi.
- Form usulan jurnal baru atau koreksi data: jurnal, field, nilai usulan, URL sumber, catatan. Masuk antrean kurasi M06 dengan status baru, ditinjau, diterapkan, ditolak.
Desain layar:
- /panduan: daftar artikel dengan tanggal terbit dan tanggal tinjau.
- Tombol "Usulkan koreksi" di setiap detail jurnal, membuka form dengan jurnal terisi.
- Admin, tab Usulan di halaman kurasi: daftar usulan, tombol terapkan yang membuka form observasi M10 terisi nilai usulan.
Kriteria selesai:
- Usulan tidak pernah langsung mengubah katalog; perubahan tetap melewati admin dan audit.
- Usulan wajib menyertakan URL sumber HTTPS.
- Throttle dan captcha sederhana atau wajib login, ditetapkan saat implementasi.
- Pengusul login dapat melihat status usulannya.

## 6. Aturan lintas modul tambahan
Aturan bagian 4 rencana sebelumnya tetap berlaku. Tambahan:
- Skema peringkat disimpan bersama periode; perubahan aturan akreditasi tidak menimpa observasi lama.
- Status integritas (discontinued, kekhawatiran evaluasi) selalu bersumber dan berperiode; tidak ada vonis tanpa bukti.
- Setiap keluaran model bahasa hanya boleh merujuk kandidat yang diambil dari sumber metadata atau katalog; rujukan di luar itu dibuang sebelum tampil.
- Data metadata pihak ketiga dipakai sesuai ketentuan masing-masing sumber dan dicatat di DATA_SOURCES.md sebelum modul terkait dirilis.
- Teks naskah, abstrak, dan daftar pustaka pengguna tidak masuk log aplikasi.
- Tidak ada angka pengguna, testimoni, penghitung kuota, atau contoh hasil berdata ilustrasi pada halaman publik.

## 7. Tahapan rilis
R6: M10 + M11. Data terstruktur dan direktori. Uji dengan katalog yang diperluas sesuai D10.
R7: M13 + M14 + M12 (tanpa daftar predator kecuali D11 diputuskan). Alat periksa tanpa AI berbayar.
R8: M15 pilot terbatas untuk pengguna login, dengan pengukuran biaya dan evaluasi manusia.
R9: M18, lalu M16 dan M17 bila R8 menunjukkan pemakaian dan biaya yang layak.

Setelah R7, periksa apakah pengguna memakai alat periksa sebelum submit. Setelah R8, periksa apakah publikasi terdekat dinilai relevan dan klaim pembeda terdukung rujukan, sebelum membuka M15 ke publik atau menjadikannya berbayar.

## 8. Keputusan terbuka
| ID | Keputusan | Rekomendasi sementara | Dampak |
|---|---|---|---|
| D10 | Cakupan katalog: tetap pendidikan atau semua bidang SINTA | Tetap pendidikan sampai sumber dan hak pakai data SINTA jelas; perluas setelah M10 | Menentukan target data dan beban kurasi; blocking R6 |
| D11 | Menerbitkan daftar jurnal/penerbit predator | Tidak menerbitkan daftar berlabel; pakai alat evaluasi dan status discontinued bersumber. Bila tetap diinginkan, hanya dari sumber resmi Kemdiktisaintek dengan tanggal dan rujukan dokumen | Risiko hukum dan akurasi (Beall's List sudah tidak dipelihara pembuatnya); blocking bagian daftar M12 |
| D12 | Penyedia model bahasa untuk M15 dan M17 | Dinoiki mendukung endpoint OpenAI-compatible; pilot M15 memakai GPT-5 mini dengan batas US$5/bulan | Menentukan provider dan batas penggunaan M15 |
| D13 | Akses dan ketentuan OpenAlex serta data retraksi Crossref | OpenAlex metadata CC0; API menyediakan kuota gratis harian dan kunci opsional; biaya dicatat sebelum pilot | OpenAlex dipakai M15; status retraksi M13 tetap perlu sumber terpisah |
| D14 | Semantic Scholar | Opsional; tunda sampai OpenAlex terbukti kurang | Tidak blocking |
| D15 | Model akses M15 | Pilot akun terdaftar, maksimal 20 analisis global per bulan, 2 per akun per hari; biaya Dinoiki dibatasi US$5/bulan | Evaluasi sebelum akses publik atau berbayar |
| D16 | Cron dan batas waktu proses di cPanel | Uji cron dan batas eksekusi PHP sebelum M16 dan sebelum menaikkan batas katalog AI | Blocking M16; memengaruhi M10 |

## 9. Prioritas berikutnya
Prioritas pertama: putuskan D10 dan D11, lalu kerjakan M10. Tanpa observasi terstruktur, filter SINTA dan tanpa APC tidak bisa dibuat jujur.
Prioritas kedua: M11, M13, M14. Fungsi CekJurnal yang paling sering dicari, tanpa biaya AI.
Prioritas ketiga: verifikasi D12 dan D13, lalu M15 sebagai pilot.

Jadwal dan biaya belum ditetapkan karena D10 sampai D16 belum dijawab.
