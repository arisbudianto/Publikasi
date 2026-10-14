@extends('layout') @section('title','Verifikasi email')
@section('content')<section class="panel login"><h1>Verifikasi email</h1><p>Kami mengirim tautan verifikasi ke <strong>{{ auth()->user()->email }}</strong>. Buka tautan itu sebelum memakai menu akun. Periksa folder spam jika belum terlihat.</p>
@if(config('account.email_enabled'))<form method="post" action="{{ route('verification.send') }}">@csrf<button type="submit">Kirim ulang email</button></form>@endif
<form method="post" action="{{ route('account.logout') }}">@csrf<button type="submit" class="secondary">Keluar</button></form></section>@endsection
