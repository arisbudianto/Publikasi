<form method="post" action="{{ route('assistant.answer') }}" class="panel">
    @csrf
    <label for="question">Pertanyaan</label>
    <textarea id="question" name="question" rows="4" minlength="12" maxlength="500" required placeholder="Contoh: Apa cakupan jurnal pendidikan dasar ini dan bagaimana memeriksa biaya publikasinya?">{{ old('question', $question ?? '') }}</textarea>
    <button type="submit">Cari jawaban</button>
</form>
