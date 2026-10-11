<?php
// Run manually in cPanel Terminal. Never put passwords in command arguments.
require __DIR__.'/../vendor/autoload.php';
function secret(string $label): string {
    fwrite(STDOUT, $label.': ');
    system('stty -echo');
    try { $value = trim(fgets(STDIN)); } finally { system('stty echo'); fwrite(STDOUT, "\n"); }
    if (strlen($value) < 12) { throw new RuntimeException('Password minimal 12 karakter.'); }
    return $value;
}
function api(string $action, array $args): void {
    $cmd = ['/usr/local/cpanel/bin/uapi', '--output=json', 'Mysql', $action];
    foreach ($args as $k => $v) { $cmd[] = $k.'='.$v; }
    $proc = proc_open($cmd, [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    $result = json_decode(stream_get_contents($pipes[1]), true);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
    if (($result['result']['status'] ?? 0) !== 1) { throw new RuntimeException('cPanel gagal pada '.$action.'. Periksa database/user sebelum mengulang.'); }
}
try {
    fwrite(STDOUT, "Provision khusus speakver_publikasi. Jangan gunakan database situs lain.\n");
    $dbPassword = secret('Password baru user database speakver_pubapp');
    fwrite(STDOUT, 'Email admin: '); $email = trim(fgets(STDIN));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Email tidak valid.'); }
    $adminPassword = secret('Password baru admin Publikasi');
    fwrite(STDOUT, 'Buat database dan user baru dengan akses hanya ke Publikasi? ketik YA: ');
    if (trim(fgets(STDIN)) !== 'YA') { exit("Dibatalkan.\n"); }
    api('create_database', ['name'=>'speakver_publikasi']);
    api('create_user', ['name'=>'speakver_pubapp','password'=>$dbPassword]);
    api('set_privileges_on_database', ['user'=>'speakver_pubapp','database'=>'speakver_publikasi','privileges'=>'ALL PRIVILEGES']);
    $path = __DIR__.'/../.env'; $env = file_get_contents($path);
    foreach (['DB_DATABASE'=>'speakver_publikasi','DB_USERNAME'=>'speakver_pubapp','DB_PASSWORD'=>$dbPassword,'SESSION_SECURE_COOKIE'=>'true'] as $k=>$v) {
        $value = '"'.str_replace(['\\','"','$'], ['\\\\','\\"','\\$'], $v).'"';
        if (preg_match('/^'.preg_quote($k,'/').'=/m',$env)) { $env = preg_replace_callback('/^'.preg_quote($k,'/').'=.*$/m', fn()=> $k.'='.$value, $env); }
        else { $env .= "\n".$k.'='.$value."\n"; }
    }
    file_put_contents($path,$env); chmod($path,0600); unset($dbPassword,$env);
    $app = require __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap();
    if ($kernel->call('migrate',['--force'=>true]) !== 0) { throw new RuntimeException('Migrasi gagal.'); }
    if ($kernel->call('db:seed',['--class'=>'PilotJournalSeeder','--force'=>true]) !== 0) { throw new RuntimeException('Seed gagal.'); }
    if (App\Models\User::where('email',$email)->exists()) { throw new RuntimeException('Admin sudah ada, tidak diubah.'); }
    $user = new App\Models\User; $user->name='Admin Publikasi'; $user->email=$email; $user->password=$adminPassword; $user->is_admin=true; $user->save(); unset($adminPassword);
    $kernel->call('config:cache'); $kernel->call('route:cache'); $kernel->call('view:cache'); $kernel->call('up');
    fwrite(STDOUT,"Provision selesai. Jurnal: ".App\Models\Journal::count().". Silakan verifikasi katalog dan login admin.\n");
} catch (Throwable $e) { fwrite(STDERR, $e instanceof RuntimeException ? $e->getMessage()."\n" : "Provision gagal; situs tetap perlu diperiksa.\n"); exit(1); }
