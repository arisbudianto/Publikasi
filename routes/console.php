<?php

use App\Models\User;
use App\Services\JournalAccreditationLinker;
use App\Services\JournalMonitoringAgent;
use App\Services\OperationalHealth;
use App\Services\ScopusDiscontinuedDataset;
use App\Services\ScopusSourceDataset;
use App\Services\SintaAccreditationDataset;
use App\Services\SintaDataset;
use App\Services\TopicMonitoring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('m16:tick', function (TopicMonitoring $monitor) {
    $this->line(json_encode($monitor->tick()));
})->purpose('Catat heartbeat cron dan periksa topik yang jatuh tempo dengan kuota');

Schedule::command('m16:tick')->everyFifteenMinutes()->withoutOverlapping(10);

Artisan::command('m19:tick', function (JournalMonitoringAgent $agent) {
    $this->line(json_encode($agent->tick()));
})->purpose('Periksa halaman jurnal yang disetujui dan catat kandidat perubahan');

Schedule::command('m19:tick')->monthlyOn(1, '03:00')->withoutOverlapping(30);

Artisan::command('publikasi:admin {email}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Email tidak valid.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Akun sudah ada; tidak diubah.');

        return 1;
    }
    $password = $this->secret('Password baru (minimal 12 karakter)');
    if (strlen($password ?? '') < 12) {
        $this->error('Password terlalu pendek.');

        return 1;
    }
    $user = new User;
    $user->name = 'Admin Publikasi';
    $user->email = $email;
    $user->password = $password;
    $user->is_admin = true;
    $user->save();
    $this->info('Admin berhasil dibuat.');
})->purpose('Buat admin melalui terminal aman, tanpa password default');

Artisan::command('publikasi:health {--json}', function (OperationalHealth $health) {
    $result = $health->inspect();
    if ($this->option('json')) {
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
    } else {
        $this->table(['Pemeriksaan', 'Status'], collect($result['checks'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line('Tidak memverifikasi backup, SMTP, scheduler, atau gateway pembayaran.');
    }

    return $result['healthy'] ? 0 : 1;
})->purpose('Pemeriksaan operasional terbatas tanpa menampilkan rahasia');

Artisan::command('publikasi:import-sinta {--apply}', function (SintaDataset $dataset) {
    $p = $dataset->preview();
    $this->line(json_encode(['total' => count($p['rows']), 'education' => $p['manifest']['education'], 'unnamed' => $p['manifest']['unnamed'], 'new' => $p['new'], 'existing' => $p['existing'], 'captured_at' => $p['manifest']['captured_at'], 'sha256' => $p['manifest']['raw_sha256']]));
    if ($this->option('apply')) {
        $this->line(json_encode($dataset->import()));
    } else {
        $this->line('DRY RUN: tidak ada penulisan. Gunakan --apply untuk impor additive.');
    }
})->purpose('Impor snapshot SINTA pemilik, idempotent tanpa menimpa katalog');

Artisan::command('publikasi:import-sinta-accreditation {--apply}', function (SintaAccreditationDataset $dataset) {
    $preview = $dataset->preview();
    $this->line(json_encode([
        'total' => count($preview['rows']),
        'rank_counts' => $preview['manifest']['rank_counts'],
        'new' => $preview['new'],
        'existing' => $preview['existing'],
        'matches' => $preview['matches'],
        'decree' => $preview['manifest']['decree_number'],
        'decree_date' => $preview['manifest']['decree_date'],
        'source_pdf_sha256' => $preview['manifest']['source_pdf_sha256'],
    ]));
    if ($this->option('apply')) {
        $this->line(json_encode($dataset->import()));
    } else {
        $this->line('DRY RUN: tidak ada penulisan. Gunakan --apply untuk impor additive.');
    }
})->purpose('Impor keputusan akreditasi SINTA dari dokumen resmi dengan tautan ISSN dan jejak sumber');

Artisan::command('publikasi:link-sinta-accreditations {--apply}', function (JournalAccreditationLinker $linker) {
    $preview = $linker->preview();
    $this->line(json_encode($preview));
    if ($this->option('apply')) {
        $this->line(json_encode($linker->apply()));
    } else {
        $this->line('DRY RUN: tidak ada penulisan. Gunakan --apply untuk menautkan hanya kecocokan ISSN yang valid dan tunggal.');
    }
})->purpose('Pratinjau atau tautkan keputusan SINTA yang belum terhubung ke jurnal kurasi melalui ISSN valid');

Artisan::command('publikasi:import-scopus-discontinued {file} {--source-as-of=} {--apply}', function (ScopusDiscontinuedDataset $dataset) {
    $sourceAsOf = (string) $this->option('source-as-of');
    if ($sourceAsOf === '') {
        $this->error('Isi --source-as-of=YYYY-MM-DD sesuai tanggal pada daftar resmi.');

        return 1;
    }
    $preview = $dataset->preview($this->argument('file'), $sourceAsOf);
    $this->line(json_encode(['rows' => $preview['total'], 'valid' => count($preview['rows']), 'errors' => $preview['errors'], 'source_as_of' => $preview['source_as_of'], 'sha256' => $preview['sha256']]));
    if ($preview['errors']) {
        $this->error('Impor dibatalkan: perbaiki baris bermasalah dan jalankan ulang preview.');

        return 1;
    }
    if (! $this->option('apply')) {
        $this->line('DRY RUN: database tidak diubah. Tambahkan --apply hanya setelah meninjau ringkasan dan checksum.');

        return 0;
    }
    $this->line(json_encode($dataset->import($preview['rows'], $sourceAsOf)));
})->purpose('Pratinjau atau impor snapshot CSV official Scopus discontinued berdasarkan ISSN');

Artisan::command('publikasi:import-scopus-sources {file} {--snapshot-month=} {--apply}', function (ScopusSourceDataset $dataset) {
    $preview = $dataset->preview($this->argument('file'), (string) $this->option('snapshot-month'));
    $this->line(json_encode(['valid' => count($preview['rows']), 'error_count' => $preview['error_count'], 'errors' => $preview['errors'], 'sha256' => $preview['sha256'], 'snapshot_month' => $preview['snapshot_month']]));
    if ($preview['error_count']) return 1;
    if (! $this->option('apply')) {
        $this->line('DRY RUN: tidak ada penulisan.');
        return 0;
    }
    $this->line(json_encode($dataset->import($preview['rows'], $preview['snapshot_month'])));
})->purpose('Pratinjau atau impor daftar sumber Scopus dari CSV snapshot resmi');
