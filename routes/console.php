<?php

use App\Models\User;
use App\Services\OperationalHealth;
use App\Services\SintaAccreditationDataset;
use App\Services\SintaDataset;
use Illuminate\Support\Facades\Artisan;

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
