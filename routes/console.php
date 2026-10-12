<?php

use App\Models\User;
use App\Services\OperationalHealth;
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
