(() => {
    const works = [...document.querySelectorAll('[data-m15-work]')];
    const escapeBib = (value) => String(value || '').replace(/[{}\\]/g, '\\$&').replace(/[\r\n]+/g, ' ');
    const keyFor = (work, index) => {
        const doi = (work.dataset.doi || '').split('/').pop().replace(/[^a-z0-9]/gi, '');
        return (doi || `openalex${index + 1}`).slice(0, 32);
    };
    const download = (name, text, type) => {
        const url = URL.createObjectURL(new Blob([text], { type }));
        const link = document.createElement('a');
        link.href = url;
        link.download = name;
        document.body.append(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    };

    document.querySelectorAll('[data-m15-export]').forEach((button) => {
        button.addEventListener('click', () => {
            const format = button.dataset.m15Export;
            if (format === 'pdf') {
                window.print();
                return;
            }
            if (format === 'ris') {
                const ris = works.map((work) => [
                    'TY  - GEN',
                    `TI  - ${work.dataset.title || ''}`,
                    work.dataset.year ? `PY  - ${work.dataset.year}` : '',
                    work.dataset.venue ? `T2  - ${work.dataset.venue}` : '',
                    work.dataset.doi ? `DO  - ${work.dataset.doi}` : '',
                    `UR  - ${work.dataset.url || work.dataset.id}`,
                    'ER  -',
                ].filter(Boolean).join('\r\n')).join('\r\n\r\n');
                download('posisi-riset.ris', `${ris}\r\n`, 'application/x-research-info-systems;charset=utf-8');
                return;
            }
            const bib = works.map((work, index) => {
                const fields = [
                    `  title = {${escapeBib(work.dataset.title)}}`,
                    work.dataset.year ? `  year = {${work.dataset.year}}` : '',
                    work.dataset.venue ? `  journal = {${escapeBib(work.dataset.venue)}}` : '',
                    work.dataset.doi ? `  doi = {${escapeBib(work.dataset.doi)}}` : '',
                    `  url = {${escapeBib(work.dataset.url || work.dataset.id)}}`,
                ].filter(Boolean).join(',\n');
                return `@misc{${keyFor(work, index)},\n${fields}\n}`;
            }).join('\n\n');
            download('posisi-riset.bib', `${bib}\n`, 'application/x-bibtex;charset=utf-8');
        });
    });

    document.querySelector('[data-m15-copy]')?.addEventListener('click', async (event) => {
        const text = document.querySelector('#m15-novelty-draft')?.textContent?.trim() || '';
        const button = event.currentTarget;
        try {
            await navigator.clipboard.writeText(text);
            button.textContent = 'Tersalin';
        } catch {
            button.textContent = 'Salin gagal';
        }
    });
})();
