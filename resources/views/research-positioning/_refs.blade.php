<p class="m15-cited">Pembanding:
@foreach($ids as $id)
    @php($source = collect($works)->firstWhere('id', $id))
    @if($source)<a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] }}</a>@if(!$loop->last); @endif @endif
@endforeach
</p>
