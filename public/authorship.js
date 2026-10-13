(() => {
    const form = document.getElementById('authorship-form');
    if (!form) return;

    const peopleList = document.getElementById('people-list');
    const template = document.getElementById('person-template');
    const addButton = document.getElementById('add-person');
    const count = document.getElementById('person-count');
    let nextIndex = Math.max(-1, ...[...peopleList.querySelectorAll('[id^="person-"]')]
        .map((input) => Number((input.id.match(/^person-(\d+)-/) || [])[1] || -1))) + 1;

    const updateRows = () => {
        const rows = [...peopleList.querySelectorAll('[data-person]')];
        count.textContent = `${rows.length} dari 30 orang`;
        addButton.disabled = rows.length >= 30;
        rows.forEach((row, index) => {
            row.querySelector('[data-person-number]').textContent = `Kontributor ${index + 1}`;
            row.querySelector('[data-remove-person]').hidden = rows.length <= 1;
        });
    };

    addButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name], [id], [for]').forEach((element) => {
            for (const attribute of ['name', 'id', 'for']) {
                if (element.hasAttribute(attribute)) {
                    element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(nextIndex)));
                }
            }
        });
        peopleList.append(fragment);
        nextIndex += 1;
        updateRows();
        peopleList.lastElementChild.querySelector('input[type="text"]').focus();
    });

    peopleList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-person]');
        if (!button || peopleList.querySelectorAll('[data-person]').length <= 1) return;
        button.closest('[data-person]').remove();
        updateRows();
    });

    const copyButton = document.getElementById('copy-statement');
    if (copyButton) {
        copyButton.addEventListener('click', async () => {
            const feedback = document.getElementById('copy-feedback');
            try {
                await navigator.clipboard.writeText(document.getElementById('contribution-statement').textContent.trim());
                feedback.textContent = 'Pernyataan disalin.';
            } catch (_) {
                feedback.textContent = 'Clipboard tidak tersedia. Pilih dan salin teks secara manual.';
            }
        });
    }

    updateRows();
})();
