# M00 Sumber data dan kontrak kurasi
Pemeriksaan dokumentasi publik: 2026-10-11. Tidak ada dataset jurnal riil diimpor dalam M00.

## Strategi
Mulai dari katalog kurasi manual pendidikan/teknologi pendidikan, usulan 30 sampai 50 jurnal. Data harus lolos review sebelum dipublikasikan.
Identitas jurnal dicocokkan melalui ISSN, penerbit dan URL resmi; jangan hanya nama.

| Informasi | Sumber utama | Metode MVP | Status kelayakan |
|---|---|---|---|
| Identitas/scope/jenis artikel | Situs resmi jurnal/penerbit | Kurasi ringkasan dan tautan | Per jurnal perlu diperiksa |
| SINTA dan masa akreditasi | Portal resmi SINTA dan dokumen akreditasi | Verifikasi manual + bukti periode | Konten portal belum berhasil diverifikasi pada sesi ini |
| Scopus/coverage/discontinued | Scopus Sources/daftar resmi Elsevier | Manual atau daftar yang diizinkan | Akses konten dan izin bulk belum diverifikasi |
| WoS/collection | Clarivate Master Journal List | Manual sumber resmi | Halaman terdeteksi; konten/detail lisensi belum diverifikasi |
| DOAJ | DOAJ | CSV/API sebagai kandidat, kurasi awal manual | Dokumentasi export tersedia; ketentuan penggunaan perlu dicatat |
| APC/jalur/waiver | Halaman biaya penerbit | Manual, mata uang asli dan tanggal | Ketersediaan berbeda per jurnal |
| Statistik waktu | Halaman statistik resmi jurnal | Manual per jenis metrik dan periode | Tidak tersedia berarti unknown |
| Artikel/DOI | Crossref atau OpenAlex | Enrichment tahap M04 | Dokumentasi tersedia; cakupan/akses diuji saat integrasi |
| Quartile | Sumber metrik resmi yang disebut | Manual dengan tahun/kategori/metric | Jangan menyamakan SJR dan JCR |

## Bukti dokumentasi yang sudah diperiksa
- DOAJ: https://doaj.org/docs/public-data-dump/
  Halaman menyediakan opsi CSV jurnal. Akses full dump dinyatakan diberikan case-by-case. Karena itu jangan mengasumsikan dump penuh langsung tersedia.
  Lisensi wajib diperiksa pada dataset/rute yang dipakai; ketentuan metadata artikel tidak otomatis menjadi ketentuan metadata jurnal.
- Crossref: https://www.crossref.org/documentation/retrieve-metadata/rest-api/
  REST API menyajikan metadata yang didepositkan anggota/sumber tepercaya dan dapat diakses tanpa signup menurut dokumentasi. Sebagian abstrak dapat memiliki hak cipta, sehingga jangan mengasumsikan bebas menyimpan semua abstrak artikel.
  Crossref tidak dijadikan bukti indeksasi Scopus/WoS/SINTA.
- OpenAlex: https://help.openalex.org/
  Dokumentasi membedakan data, API, serta access/pricing. Harga, key, kuota dan ketentuan bulk belum diperiksa; jangan menjanjikan integrasi gratis tanpa batas.

## Target verifikasi lanjutan
- https://www.scopus.com/sources
- https://mjl.clarivate.com/
- https://sinta.kemdiktisaintek.go.id/journals
URL merupakan target pemeriksaan; kegagalan akses tool bukan bukti situs mati.
Claim penerbit tentang indeksasi harus dibedakan dari konfirmasi index provider.

## Data dictionary minimum
| Field | Tipe konseptual | Required publish | Aturan |
|---|---|---|---|
| journal_name, publisher | text | Ya | Nama sesuai sumber |
| ISSN | normalized text | Minimal satu identifier sah atau pengecualian reviewer | Format dan checksum; konflik ditinjau |
| official_url | HTTPS URL | Ya | Cocok identitas; tautan kandidat belum dianggap resmi |
| scope_summary, subject_tags | text/list | Ya | Parafrasa sumber, bukan klaim AI |
| article_types, languages, context_tags | list nullable | Tidak | Unknown eksplisit |
| index, status, coverage/period | observation | Jika menampilkan indeksasi | Evidence diperlukan |
| metric, rank, year, category | observation | Jika menampilkan peringkat | Jangan simpan rank tanpa konteks |
| amount, currency, route, fee_type | observation nullable | Tidak | Nol hanya bukti eksplisit |
| process_type, value, unit, period | observation nullable | Tidak | Bedakan keputusan pertama dan review |
| source_url, checked_at, reviewed_by | evidence | Ya untuk fakta yang tampil | Waktu ISO 8601 UTC; tanggal berlaku terpisah |
| reuse_status, license_url | text/URL | Ya untuk impor eksternal | approved/manual-summary/pending; pending tidak dipublish bulk |

## SOP kurasi
1. Identifikasi jurnal dan official_url.
2. Catat sumber scope, konteks, jenis artikel dan bahasa.
3. Periksa setiap indeksasi secara terpisah, termasuk periode.
4. Catat biaya per jalur, waiver bersyarat, dan biaya tambahan.
5. Catat statistik waktu hanya bila sumber menjelaskan metric/period.
6. Tulis unknown pada data yang tidak tersedia.
7. Reviewer memeriksa identitas, hak penggunaan, bukti, dan konflik.
8. Publish; simpan riwayat observasi dan tanggal review.

Usulan interval review operasional: biaya 90 hari, indeksasi 90 hari, scope 180 hari. Ini aturan produk, bukan interval update penyedia. Data lama diberi badge perlu diperiksa, tidak otomatis dicabut.
Temuan konflik menampilkan sumber dan tanggal. Tidak menghapus observasi lama atau menyimpulkan yang paling baru pasti benar tanpa review.

## Pilot dan kualitas
Usulan komposisi 40 jurnal: nasional dan internasional, konteks SD/menengah/tinggi/vokasi, jalur biaya beragam.
Tidak wajib semua memiliki semua field. Semua jurnal harus memiliki identitas dan scope bersumber.
Inventaris dataset pengguna belum diketahui. Tidak memasukkan testimoni, angka pengguna, peluang diterima, atau database referensi.
