(() => {
    const form = document.getElementById('reference-form');
    if (!form) return;
    const input = document.getElementById('reference-input');
    const consent = document.getElementById('reference-consent');
    const submit = document.getElementById('reference-submit');
    const progress = document.getElementById('reference-progress');
    const count = document.getElementById('reference-count');
    const error = document.getElementById('reference-error');
    const results = document.getElementById('reference-results');
    const rows = document.getElementById('reference-rows');
    const summary = document.getElementById('reference-summary');
    const csrf = form.querySelector('[name="_token"]').value;
    let checked = [];

    const clean = (s) => s.replace(/\s+/g, ' ').trim();
    const splitReferences = (text) => {
        const lines = text.replace(/\r\n?/g, '\n').trim().split('\n');
        const numbered = lines.some((line) => /^\s*(?:\[\d+\]|\d{1,3}[.)])\s+/.test(line));
        const entries = [];
        let current = '';
        for (const line of lines) {
            const start = line.match(/^\s*(?:\[\d+\]|\d{1,3}[.)])\s+(.*)$/);
            if (numbered && start) {
                if (current.trim()) entries.push(clean(current));
                current = start[1];
            } else if (!line.trim()) {
                if (current.trim()) entries.push(clean(current));
                current = '';
            } else if (!current) current = line.trim();
            else if (numbered || /^\s/.test(line)) current += ` ${clean(line)}`;
            else { entries.push(clean(current)); current = line.trim(); }
        }
        if (current.trim()) entries.push(clean(current));
        return entries.filter(Boolean);
    };
    const el = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined && text !== null) node.textContent = String(text);
        if (className) node.className = className;
        return node;
    };
    const statusLabels = {
        matched: 'Cocok', matched_with_difference: 'Cocok dengan perbedaan', possible_match: 'Kemungkinan cocok',
        not_found: 'Tidak ditemukan di Crossref', unavailable: 'Pencarian gagal',
    };
    function renderRow(item, index) {
        const tr = document.createElement('tr');
        tr.append(el('td', index + 1)); tr.append(el('td', item.reference));
        const statusCell = document.createElement('td');
        statusCell.append(el('span', statusLabels[item.result.match_status] || item.result.match_status, `reference-status status-${item.result.match_status}`));
        const updateLabel = { retracted: 'Ada pemberitahuan pencabutan', corrected: 'Ada pemberitahuan koreksi', expression_of_concern: 'Ada expression of concern' }[item.result.update_status];
        if (updateLabel) statusCell.append(el('p', updateLabel));
        tr.append(statusCell);
        const metadataCell = document.createElement('td');
        const metadata = item.result.metadata;
        if (metadata) {
            const link = el('a', metadata.title); link.href = metadata.doi_url; link.target = '_blank'; link.rel = 'noopener noreferrer'; metadataCell.append(link);
            metadataCell.append(el('p', `${metadata.authors.join(', ')}${metadata.year ? ` (${metadata.year})` : ''}${metadata.journal ? `. ${metadata.journal}` : ''}`));
            metadataCell.append(el('p', `DOI: ${metadata.doi}`));
            if (item.result.title_similarity !== null) metadataCell.append(el('p', `Kemiripan judul: ${item.result.title_similarity}%`));
        } else metadataCell.append(el('span', 'Metadata tidak tersedia'));
        tr.append(metadataCell);
        const differenceCell = document.createElement('td');
        (item.result.differences || []).forEach((text) => differenceCell.append(el('p', text)));
        (item.result.updates || []).forEach((update) => {
            const link = el('a', `${update.label} (${update.source})`); link.href = update.notice_url; link.target = '_blank'; link.rel = 'noopener noreferrer'; differenceCell.append(link);
        });
        if (!differenceCell.childNodes.length) differenceCell.textContent = '—';
        tr.append(differenceCell);
        return tr;
    }
    function exportable() { return checked.filter((item) => ['matched', 'matched_with_difference'].includes(item.result.match_status) && item.result.metadata); }
    function csvEscape(value) {
        let text = String(value ?? '');
        if (/^[=+\-@]/.test(text)) text = `'${text}`;
        return `"${text.replace(/"/g, '""')}"`;
    }
    function download(name, content, type) {
        const blob = new Blob([content], { type }); const url = URL.createObjectURL(blob);
        const link = document.createElement('a'); link.href = url; link.download = name; link.click(); URL.revokeObjectURL(url);
    }
    document.getElementById('export-csv').addEventListener('click', () => {
        const data = exportable();
        const lines = [['judul', 'penulis', 'tahun', 'jurnal', 'doi', 'status_pembaruan'], ...data.map(({ result }) => {
            const m = result.metadata; return [m.title, m.authors.join('; '), m.year, m.journal, m.doi, result.update_status || ''];
        })].map((row) => row.map(csvEscape).join(','));
        download('referensi-terverifikasi.csv', `\uFEFF${lines.join('\r\n')}`, 'text/csv;charset=utf-8');
    });
    document.getElementById('export-ris').addEventListener('click', () => {
        const content = exportable().map(({ result }) => {
            const m = result.metadata; const lines = ['TY  - JOUR', `TI  - ${m.title}`, ...m.authors.map((name) => `AU  - ${name}`)];
            if (m.year) lines.push(`PY  - ${m.year}`); if (m.journal) lines.push(`JO  - ${m.journal}`);
            lines.push(`DO  - ${m.doi}`, 'ER  -', ''); return lines.join('\r\n');
        }).join('\r\n');
        download('referensi-terverifikasi.ris', content, 'application/x-research-info-systems;charset=utf-8');
    });
    input.addEventListener('input', () => {
        const n = splitReferences(input.value).length; count.textContent = `${n} entri`;
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); error.hidden = true; rows.replaceChildren(); checked = [];
        const references = splitReferences(input.value);
        if (!references.length) { error.textContent = 'Masukkan setidaknya satu referensi.'; error.hidden = false; return; }
        if (input.value.length > 30000 || references.length > 100) { error.textContent = 'Batas input adalah 100 entri dan 30.000 karakter.'; error.hidden = false; return; }
        if (!consent.checked) { error.textContent = 'Centang persetujuan pengiriman referensi ke Crossref.'; error.hidden = false; return; }
        submit.disabled = true; input.disabled = true; consent.disabled = true; results.hidden = false;
        let succeeded = 0;
        for (let i = 0; i < references.length; i += 1) {
            progress.textContent = `Memeriksa ${i + 1} dari ${references.length}…`;
            let result;
            try {
                const response = await fetch('/cek-referensi/periksa-satu', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ reference: references[i], consent: true }),
                });
                result = response.ok ? await response.json() : { match_status: 'unavailable', metadata: null, differences: [], updates: [], update_status: null };
            } catch (_) {
                result = { match_status: 'unavailable', metadata: null, differences: [], updates: [], update_status: null };
            }
            const item = { reference: references[i], result }; checked.push(item); rows.append(renderRow(item, i)); succeeded += result.match_status === 'unavailable' ? 0 : 1;
            summary.textContent = `${succeeded} dari ${references.length} berhasil diperiksa; ${references.length - succeeded} gagal dihubungi.`;
            const hasData = exportable().length > 0;
            document.getElementById('export-csv').disabled = !hasData; document.getElementById('export-ris').disabled = !hasData;
        }
        progress.textContent = 'Pemeriksaan selesai.'; submit.disabled = false; input.disabled = false; consent.disabled = false;
    });
})();
