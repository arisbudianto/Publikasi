@extends('layout') @section('title','Lupa password')
@section('content')<section class="panel login"><h1>Lupa password</h1>
@if(config('account.email_enabled'))<p>Masukkan email akun. Kami akan mengirim tautan untuk membuat password baru.</p>
<form method="post" action="{{ route('password.email') }}">@csrf<label>Email<input type="email" name="email" maxlength="255" required autocomplete="email" value="{{ old('email') }}"></label><button>Kirim tautan pemulihan</button></form>
@else<p>Pengiriman email belum aktif, sehingga pemulihan password belum tersedia. Hubungi pengelola situs untuk bantuan akun.</p>@endif
<p><a href="{{ route('account.login') }}">Kembali ke login</a></p></section>@endsection
