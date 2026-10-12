@extends('layout') @section('title',$register?'Daftar akun':'Login pengguna')
@section('content')<section class="panel login"><h1>{{ $register?'Daftar akun':'Login pengguna' }}</h1><p>Akun menyimpan shortlist dan riwayat pilihan Anda. Katalog tetap terbuka tanpa akun.</p>
@if(isset($failure))<div class="error" role="alert">{{ $failure }}</div>@endif
@if(isset($inputErrors))<div class="error" role="alert"><ul>@foreach($inputErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ $register?route('account.register'):route('account.login') }}">@csrf
@if($register)<label>Nama<input name="name" required maxlength="100" autocomplete="name"></label>@endif
<label>Email<input type="email" name="email" required maxlength="255" autocomplete="username"></label>
<label>Password<input type="password" name="password" required @if($register) minlength="12" @endif maxlength="128" autocomplete="{{ $register?'new-password':'current-password' }}"></label>
@if($register)<label>Ulangi password<input type="password" name="password_confirmation" required minlength="12" maxlength="128" autocomplete="new-password"></label>@endif
<button>{{ $register?'Buat akun':'Masuk' }}</button></form><p><a href="{{ $register?route('account.login'):route('account.register') }}">{{ $register?'Sudah punya akun? Login':'Belum punya akun? Daftar' }}</a></p><p class="muted">Verifikasi email dan pemulihan password melalui email belum tersedia. Simpan password Anda dengan aman.</p></section>@endsection
