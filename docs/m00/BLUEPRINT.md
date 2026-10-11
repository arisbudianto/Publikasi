# M00 Blueprint produk
Tanggal: 2026-10-11. Status: draft operasional, keputusan material belum dikonfirmasi.

## 1. Product objective
Membantu akademisi memilih jurnal dengan shortlist yang sesuai topik, konteks penelitian, kebutuhan indeksasi, anggaran, dan informasi proses bersumber.

## 2. Primary user dan role
Pengguna utama: dosen, peneliti, mahasiswa S2/S3 dengan judul dan abstrak.
Pengunjung dapat membaca katalog dan melakukan pencocokan sementara sebagai usulan MVP.
Admin global mengelola katalog dan observasi data. Login admin wajib.
Akun peneliti dan riwayat pribadi ditunda; keputusan akses publik/pribadi tetap terbuka.
Tidak ada tenant, organisasi, atau supervisor pada MVP.

| Actor | Resource | Read | Write/delete | Scope |
|---|---|---|---|---|
| Pengunjung | Katalog published | Ya | Tidak | Global |
| Pengunjung | Input pencocokan sementara | Sesi sendiri | Isi dan hapus input sendiri | Tidak persisten |
| Admin | Katalog dan evidence | Ya | CRUD dengan arsip dan audit | Global |
| Admin | Abstrak pengunjung | Tidak disediakan | Tidak | Tidak ada penyimpanan permanen MVP |

Izin admin harus diperiksa server-side. Endpoint impor juga hanya admin.

## 3. Core outcome
Pengguna menghasilkan shortlist maksimal tiga jurnal dan mengetahui alasan cocok, mismatch, sumber, tanggal data, serta informasi yang belum tersedia.
Tidak menjanjikan penerimaan artikel atau kelayakan angka kredit.

## 4. Core flows
FLOW: Pencocokan
- Actor: pengunjung.
- Trigger: memiliki judul dan abstrak.
- Happy path: isi judul/abstrak dan konteks, pilih batas wajib, jalankan pencocokan, lihat kandidat, buka detail, bandingkan.
- Important failure: input invalid, katalog tidak memenuhi batas, proses gagal.
- Success state: shortlist bersumber atau penjelasan hasil kosong; tidak ada kandidat rekaan.

FLOW: Katalog dan perbandingan
- Actor: pengunjung.
- Trigger: mencari nama/ISSN atau memeriksa beberapa jurnal.
- Happy path: cari, filter, lihat detail, pilih satu sampai tiga jurnal.
- Important failure: field belum diketahui, pilihan keempat, jurnal diarsipkan.
- Success state: tabel perbandingan yang mempertahankan unit, tahun, kategori, dan sumber.

FLOW: Kurasi
- Actor: admin.
- Trigger: data jurnal baru atau berubah.
- Happy path: input/impor preview, validasi, review evidence, publish, catat audit.
- Important failure: ISSN invalid, duplikat, konflik sumber, impor gagal.
- Success state: observasi baru published dengan provenance; data sebelumnya tidak tertimpa diam-diam.

## 5. MVP MUST
M00 blueprint dan evaluasi kelayakan.
M01 katalog dan admin, field bersumber, impor CSV tervalidasi.
M02 filter dan perbandingan maksimal tiga jurnal.
M03 pencocokan dasar tanpa API AI: keyword/tag scope, jenis artikel, populasi, metode dan konteks.
Keamanan, logging tanpa abstrak, backup, dan uji restore termasuk kebutuhan rilis awal.

## 6. SHOULD/LATER
M04 semantik/AI dan artikel pendukung; M05 akun serta shortlist opt-in; M06 pemutakhiran otomatis; M07 checklist jurnal; M08 submission dan reviewer; M09 monetisasi opsional.
Kurasi manual dan provenance M06 tetap dimulai di M01.

## 7. Out of scope
Scraping massal default, database JournalMatch, peniruan merek/desain, upload naskah penuh, prediksi peluang diterima, submit otomatis, pembayaran, email reviewer, pemeriksaan mutu metodologi penuh.
Tidak mengambil data berlisensi hanya karena terlihat publik.

## 8. Conceptual entities
| Entity | Purpose/key fields | Ownership/relasi/lifecycle |
|---|---|---|
| Journal | UUID, nama, penerbit, official_url, language, article_types, context_tags | Global; draft/reviewed/published/archived |
| Identifier | ISSN print/electronic/ISSN-L, external_id | Milik jurnal; nilai normalisasi unik per tipe/sumber; konflik ditinjau |
| ScopeObservation | ringkasan scope, subject_tags, context_constraints, observed_at | Journal + Evidence; tidak overwrite riwayat |
| IndexObservation | index, status, coverage_from/to, accreditation_period | Journal + Evidence; status unknown/active/inactive/discontinued sesuai bukti |
| RankObservation | metric, value, year, category | Journal + Evidence; pisahkan SJR/CiteScore/JCR/SINTA |
| FeeObservation | amount, currency, fee_type, route, waiver, effective date | Journal + Evidence; null bukan nol |
| ProcessObservation | metric_type, value/unit/range, reporting_period | Journal + Evidence; first decision/review/acceptance/publication terpisah |
| Evidence | source_url, source_name, checked_at, reviewer, reuse_status, excerpt/paraphrase | Global; tanggal pemeriksaan berbeda dari tanggal berlaku |
| AdminUser/ChangeLog | autentikasi, actor, perubahan, timestamp | Password hash; jangan simpan secret/log abstrak |
| MatchRequest/Result | input sementara, candidate IDs, rules_version, alasan | Request scoped; tidak database abstrak MVP |

Ini kontrak konseptual, belum migration atau skema SQL final.

## 9. Business rules
- Rekomendasi hanya jurnal published dalam katalog.
- Hard filter dahulu, ranking kemudian; user boleh memilih syarat all/any indeksasi, default all.
- Unknown tidak memenuhi syarat wajib, termasuk biaya/waktu.
- Rank bertahun/kategori; tidak merangkum quartile menjadi fakta tunggal tanpa sumber.
- Arsip berbeda dari discontinued; arsip adalah lifecycle internal.
- Tanpa APC harus terikat jalur publikasi dan biaya lain yang diketahui.
- Skor aturan bukan peluang penerimaan; tidak menampilkan persentase peluang.
- Scope pendidikan umum tidak membuktikan konteks SD diterima; unknown context harus ditandai perlu diperiksa.
- Artikel pendukung bukan bukti otomatis mutu metode.
- Admin tidak mengubah fakta sumber tanpa audit; konflik dipertahankan.

## 10. Integrations
MVP: impor manual/CSV dan tautan sumber resmi; tidak memerlukan koneksi API saat pengguna mencari.
DOAJ/OpenAlex/Crossref adalah kandidat enrichment tahap berikutnya, dengan pemeriksaan ketentuan, akses, dan field.
Scopus, WoS, SINTA: observasi kurasi resmi dahulu. Tidak mengasumsikan API atau lisensi bulk.
AI, SMTP, kurs otomatis tidak diperlukan M01 sampai M03.

## 11. Material constraints
GitHub pengguna sebagai source of truth; deployment cPanel.
Usulan pilot pendidikan/teknologi pendidikan, 30 sampai 50 jurnal dan 20 kasus evaluasi.
Budget, deadline, domain scope, model akses, dataset pengguna dan runtime belum diberikan.
Rekomendasi pilot bukan persetujuan pengguna. Dataset riil belum dikurasi.

## 12. Acceptance criteria
Lihat ACCEPTANCE_CRITERIA.md untuk kasus observable dan gate.

## 13. Open/blocking decisions
Sumber data yang boleh digunakan, scope pilot, model akses, kapasitas hosting, batas biaya/jadwal.
Blueprint konten dapat direview sekarang; development belum boleh dimulai sampai blocker yang relevan selesai.
