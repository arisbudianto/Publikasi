# M03 Pencocokan dasar

POST /cocokkan menerima judul 10–300 karakter, abstrak 80–10.000, konteks opsional maksimal 1.500. Form publik menggunakan CSRF Laravel, POST throttle 20/menit; hasil dan error no-store. Input tidak ditulis ke database, session old_input, file atau layanan eksternal oleh aplikasi. Logging infrastruktur hosting di luar kontrol aplikasi belum diaudit.

Aturan m03-keywords-1: lowercase/ascii, buang markup dan stopword umum; istilah unik dengan padanan bilingual terbatas. Irisan scope_summary + subject, tidak memakai nama/penerbit jurnal sebagai evidence. Bobot judul 3, abstrak 1, konteks 1. Hanya published dengan irisan judul/abstrak; konteks saja tidak membuat kandidat. Urutan deterministik skor menurun lalu ID, maksimal 10, proses chunk 100. Tidak memakai API AI, embedding atau layanan berbayar.

Hasil menjelaskan irisan tiap input, sumber scope, tanggal pemeriksaan, konteks belum terverifikasi dan tautan memulai perbandingan M02. Angka skor internal adalah urutan aturan, bukan persentase diterima. Tidak menyimpulkan kecocokan jenjang/metode/jenis artikel tanpa data. Tidak mengklaim akurasi semantik; sinonim/morfologi di luar daftar dapat terlewat.

TESTED: 22 tests / 145 assertions PASS, PHP 8.3: bilingual, provenance, judul lebih berbobot, urutan/limit, draft/arsip, konteks saja, hasil kosong, input invalid tanpa flash, repetisi/stopword/XSS, throttle, regresi M01/M02.
DATA IMPACT: READ_ONLY; tidak ada migration/dependency/credential baru.
ROLLBACK: pulihkan routes/web.php, public/catalog.css, layout dan katalog index dari 1f0d5da; rebuild route/view cache. Hapus rute baru sehingga file controller/service/template baru tidak dipanggil.
NOT VERIFIED: kualitas pada korpus naskah nyata, logging infrastruktur, live flow sebelum deployment.
