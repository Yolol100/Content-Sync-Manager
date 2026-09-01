document.addEventListener('DOMContentLoaded', function () {
    const settings = window.dcaTbSettings || {};
    const nonce = settings.nonce;
    const ajaxUrl = window.ajaxurl || settings.ajaxUrl || '';

    if (!nonce || !ajaxUrl) {
        return;
    }

    const toolbar = document.querySelector('.dca-toolbar, .dca-tb-toolbar, [data-dca-toolbar]');
    if (!toolbar) {
        return;
    }

    if (toolbar.querySelector('[data-dca-ai-image-context]')) {
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button';
    button.dataset.dcaAiImageContext = '1';
    button.textContent = 'AI afbeeldingen export';

    const status = document.createElement('span');
    status.className = 'screen-reader-text';
    status.setAttribute('aria-live', 'polite');

    toolbar.appendChild(button);
    toolbar.appendChild(status);

    function selectedPostIds() {
        return Array.from(document.querySelectorAll('input[type="checkbox"][name="post[]"]:checked'))
            .map((checkbox) => parseInt(checkbox.value, 10))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    function downloadText(text, filename) {
        const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename || 'content-sync-ai-images.txt';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    button.addEventListener('click', async function () {
        const ids = selectedPostIds();
        if (!ids.length) {
            window.alert('Selecteer eerst minimaal één pagina, bericht of product.');
            return;
        }

        button.disabled = true;
        status.textContent = 'AI-afbeeldingscontext wordt gemaakt.';

        const formData = new FormData();
        formData.append('action', 'dca_ai_image_context_export');
        formData.append('nonce', nonce);
        ids.forEach((id) => formData.append('ids[]', String(id)));

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
            const payload = await response.json();

            if (!response.ok || !payload || !payload.success || !payload.data || !payload.data.text) {
                const message = payload && payload.data && payload.data.message
                    ? payload.data.message
                    : 'AI-afbeeldingscontext exporteren is mislukt.';
                throw new Error(message);
            }

            downloadText(payload.data.text, payload.data.filename);
            status.textContent = 'AI-afbeeldingscontext is geëxporteerd.';
        } catch (error) {
            const message = error instanceof Error ? error.message : 'AI-afbeeldingscontext exporteren is mislukt.';
            window.alert(message);
            status.textContent = message;
        } finally {
            button.disabled = false;
        }
    });
});
