# M00 Acceptance criteria dan evaluasi pilot
Status semua uji aplikasi: NOT RUN. Tidak ada implementasi pada M00.
Kriteria berikut adalah spesifikasi untuk M01 sampai M03, bukan hasil test.

| ID | Skenario | Expected observable result | Tahap |
|---|---|---|---|
| AC01 | Cari nama/ISSN valid | Jurnal pilot terkait ditemukan dengan pagination | M01 |
| AC02 | Import ISSN invalid/duplikat | Preview memberi baris/alasan; tidak ada commit parsial | M01 |
| AC03 | Pengunjung mengubah katalog lewat request langsung | Ditolak server; data tidak berubah | M01 |
| AC04 | Data indeks/fee/waktu tampil | Evidence, tanggal, periode/unit yang relevan terlihat | M01 |
| AC05 | APC unknown dan filter gratis | Jurnal unknown tidak lolos sebagai gratis | M02 |
| AC06 | Pilih Scopus AND SINTA | Hanya jurnal dengan kedua bukti memenuhi periode terkait | M02 |
| AC07 | Membandingkan tiga lalu pilih keempat | Tiga tetap tersedia; pilihan keempat ditolak jelas | M02 |
| AC08 | Batas biaya/waktu tak terpenuhi | Hasil kosong dan penjelasan; filter tidak dilonggarkan diam-diam | M02/M03 |
| AC09 | Judul/abstrak kosong atau terlalu panjang | Pesan field jelas; matching tidak dijalankan | M03 |
| AC10 | SD vs jurnal eksklusif pendidikan tinggi | Mismatch ditampilkan dan kandidat tidak masuk top rekomendasi cocok | M03 |
| AC11 | Konteks scope unknown | Perlu diperiksa; tidak dinyatakan pasti cocok | M03 |
| AC12 | Input dan katalog/rules sama | Urutan hasil dan alasan sama dengan tie-break stabil | M03 |
| AC13 | Hasil matching | ID berasal katalog published; alasan berasal aturan/evidence | M03 |
| AC14 | Proses request selesai | Abstrak tidak tertulis pada DB, log, atau telemetry | M03 |
| AC15 | Ranking | Label skor aturan; tidak ada peluang penerimaan numerik | M03 |
| AC16 | Site produksi | HTTPS valid, debug false, source/secret tidak terlayani | Rilis |
| AC17 | Restore backup staging | Data pilot dan evidence kembali lengkap; produksi tidak berubah | Rilis |

## Kasus evaluasi
Usulan 20 abstrak sintetis yang diberi label jelas sebagai fixture:
- 4 SD, 4 menengah, 4 pendidikan tinggi, 4 vokasi, 4 lintas konteks/di luar pilot.
- Sertakan original research, review, metode kuantitatif/kualitatif, budget nol/terbatas, dan indeks wajib.
- Katalog evaluator berasal jurnal riil yang dikurasi, bukan jurnal sintetis di hasil publik.
- Peninjau membuat label relevan/tidak relevan/perlu diperiksa dan alasan sebelum melihat ranking.

## Usulan gate kualitas M03
Minimal 16 kasus in-scope dan 4 out-of-scope.
Sekurangnya 13 dari 16 kasus in-scope memiliki satu kandidat top-3 yang dinilai relevan.
Tidak ada rekomendasi top-3 berlabel cocok pada kasus konflik konteks eksplisit.
Seluruh hard filter dipatuhi; seluruh klaim fakta yang tampil bersumber.
Jika tidak ada jurnal relevan dalam katalog, sistem harus abstain. Kasus tersebut dicatat sebagai gap katalog, bukan dipaksakan sebagai keberhasilan ranking.
Evaluator dan threshold dikonfirmasi sebelum build; ini usulan, belum ukuran tervalidasi.

## Gate M00
- Dokumen memuat 13 unsur minimum blueprint.
- MVP, out-of-scope, ownership, privacy dan invariants jelas.
- Hak penggunaan sumber pilot dan sampel kurasi disetujui.
- Scope dan mode akses ditetapkan.
- Runtime hosting dibuktikan atau lingkungan development kompatibel ditetapkan dengan deployment risk eksplisit.
- Budget/deadline diketahui atau fallback tanpa AI/tanpa komitmen jadwal disetujui.
M00 document package dapat selesai ketika bukti belum tersedia; gate development tetap NOT READY.
