@extends('layout') @section('title',$register?'Daftar akun':'Login pengguna')
@section('content')<section class="panel login"><h1>{{ $register?'Daftar akun':'Login pengguna' }}</h1><p>Akun menyimpan shortlist dan riwayat pilihan Anda. Katalog tetap terbuka tanpa akun.</p>
@if(isset($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
@if(session('account_error'))<div class="error" role="alert">{{ session('account_error') }}</div>@endif
@if(isset($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ $register?route('account.register'):route('account.login') }}">@csrf
@if($register)<label>Nama<input name="name" required maxlength="100" autocomplete="name"></label>@endif
<label>Email<input type="email" name="email" required maxlength="255" autocomplete="username"></label>
<label>Password<input type="password" name="password" required @if($register) minlength="12" @endif maxlength="128" autocomplete="{{ $register?'new-password':'current-password' }}"></label>
@if($register)<label>Ulangi password<input type="password" name="password_confirmation" required minlength="12" maxlength="128" autocomplete="new-password"></label>@endif
<button>{{ $register?'Buat akun':'Masuk' }}</button></form>
@if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))<div class="account-divider">atau</div><a class="button secondary account-google" href="{{ route('account.google.redirect') }}">Lanjutkan dengan Google</a>@endif
<p><a href="{{ $register?route('account.login'):route('account.register') }}">{{ $register?'Sudah punya akun? Masuk':'Belum punya akun? Daftar' }}</a></p>
@unless($register)<p><a href="{{ route('password.request') }}">Lupa password?</a></p>@endunless
@if(config('account.email_enabled'))<p class="muted">Pendaftaran dengan email memerlukan verifikasi melalui tautan yang dikirim ke kotak masuk Anda.</p>@else<p class="muted">Pengiriman email belum aktif. Verifikasi email dan pemulihan password menunggu konfigurasi email.</p>@endif
</section>@endsection
