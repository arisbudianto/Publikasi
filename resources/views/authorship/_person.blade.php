<fieldset class="authorship-person" data-person>
    <legend><span data-person-number>Kontributor {{ is_numeric($index) ? $index + 1 : 'baru' }}</span></legend>
    <button class="quiet authorship-remove" type="button" data-remove-person aria-label="Hapus kontributor">Hapus</button>
    <label for="person-{{ $index }}-name">Nama atau inisial</label>
    <input id="person-{{ $index }}-name" name="people[{{ $index }}][name]" type="text" maxlength="100" value="{{ is_array($person ?? null) ? ($person['name'] ?? '') : '' }}" required autocomplete="off">

    <fieldset class="authorship-checks">
        <legend>Empat syarat ICMJE <span>Pilih semua yang dipenuhi</span></legend>
        <div class="authorship-check-grid">
            @foreach($criteria as $key => $label)
                <label class="authorship-check" for="person-{{ $index }}-{{ $key }}">
                    <input id="person-{{ $index }}-{{ $key }}" type="checkbox" name="people[{{ $index }}][criteria][]" value="{{ $key }}" @checked(is_array($person['criteria'] ?? null) && in_array($key, $person['criteria'], true))>
                    <span><strong>{{ strtoupper($key) }}.</strong> {{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <details class="authorship-role-details">
        <summary>Peran kontribusi CRediT</summary>
        <div class="authorship-role-grid">
            @foreach($roles as $key => $label)
                <label class="authorship-check" for="person-{{ $index }}-role-{{ $key }}">
                    <input id="person-{{ $index }}-role-{{ $key }}" type="checkbox" name="people[{{ $index }}][roles][]" value="{{ $key }}" @checked(is_array($person['roles'] ?? null) && in_array($key, $person['roles'], true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </details>
</fieldset>
