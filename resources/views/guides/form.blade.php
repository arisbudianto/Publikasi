@extends('layout')
@section('title', $guide->exists ? 'Sunting panduan' : 'Tulis panduan')
@section('content')
<p><a href="{{ route('admin.guides.index') }}">Kembali ke daftar panduan</a></p><h1>{{ $guide->exists ? 'Sunting panduan' : 'Tulis panduan' }}</h1>
<form class="panel" method="post" action="{{ $guide->exists ? route('admin.guides.update', $guide) : route('admin.guides.store') }}">@csrf @if($guide->exists)@method('PUT')@endif
<label>Alamat singkat<input name="slug" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $guide->slug) }}" placeholder="biaya-publikasi"></label>
<label>Judul<input name="title" required maxlength="180" value="{{ old('title', $guide->title) }}"></label>
<label>Isi panduan, teks biasa<textarea name="body" required minlength="100" maxlength="20000" rows="18">{{ old('body', $guide->body) }}</textarea></label>
<label>Tanggal tinjau<input name="reviewed_at" type="date" max="{{ today()->format('Y-m-d') }}" value="{{ old('reviewed_at', $guide->reviewed_at?->format('Y-m-d')) }}"></label>
<label>Status<select name="status"><option value="draft" @selected(old('status', $guide->published_at ? 'published' : 'draft') === 'draft')>Draf</option><option value="published" @selected(old('status', $guide->published_at ? 'published' : 'draft') === 'published')>Terbitkan</option></select></label>
<p>Tulis berdasarkan data yang dapat diperiksa. Artikel baru terlihat publik setelah diterbitkan.</p><button>Simpan panduan</button>
</form>
@endsection
