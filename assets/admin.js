document.addEventListener('DOMContentLoaded', function () {
    const dcaSettings = window.dcaTbSettings || {};
    const nonce = dcaSettings.nonce;
    const filterUrl = dcaSettings.filterUrl;
    const filterLabel = dcaSettings.filterLabel;
    const ajaxUrl = window.ajaxurl || dcaSettings.ajaxUrl || '';
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => Array.from(document.querySelectorAll(selector));

    function clearElement(element) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function ensureToolbar() {
        if (!nonce) {
            return;
        }

        const buttonItems = [
            ['dca-copy-selected', 'Kopieer selectie', 'button'],
            ['dca-export-selected', 'Export selectie .txt', 'button'],
            ['dca-open-empty-bulk', 'Bulkeditor', 'button'],
            ['dca-deselect-selected', 'Deselecteer alles', 'button'],
            ['dca-open-import', 'Import .txt', 'button button-primary'],
            ['dca-restore-last-import', 'Herstel laatste import', 'button'],
        ];

        const existing = document.querySelector('.dca-toolbar');

        if (existing) {
            existing.remove();
        }

        const bar = document.createElement('div');
        bar.className = 'dca-toolbar';

        buttonItems.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.id = item[0];
            button.className = item[2];
            button.textContent = item[1];
            bar.appendChild(button);
        });

        const link = document.createElement('a');
        link.className = 'button dca-toolbar-filter';
        link.href = filterUrl || '#';
        link.textContent = filterLabel || 'Filter';
        bar.appendChild(link);
        document.body.appendChild(bar);
    }

    ensureToolbar();

    const toast = $('#dca-toast');
    const singleModal = $('#dca-single-modal');
    const singleOut = $('#dca-single-output');
    const singleTitle = $('#dca-single-title');
    const singleStatus = $('#dca-single-status');
    const singleView = $('#dca-single-view');
    const singleSave = $('#dca-single-save');
    const singleCopy = $('#dca-single-copy');
    const singleDownload = $('#dca-single-download');
    const singleClose = $('.dca-close-single');

    const bulkModal = $('#dca-bulk-modal');
    const bulkOut = $('#dca-bulk-output');
    const bulkStatus = $('#dca-bulk-status');
    const bulkPreview = $('#dca-bulk-preview');
    const bulkSave = $('#dca-bulk-save');
    const bulkCheck = $('#dca-bulk-check');
    const bulkCopy = $('#dca-bulk-copy');
    const bulkDownload = $('#dca-bulk-download');
    const bulkClose = $('.dca-close-bulk');

    const importModal = $('#dca-import-modal');
    const importFile = $('#dca-import-file');
    const importStatus = $('#dca-import-status');
    const importPreviewBox = $('#dca-import-preview-box');
    const importRun = $('#dca-import-run');
    const importPreview = $('#dca-import-preview');
    const importClose = $('.dca-close-import');

    const toolbarCopy = $('#dca-copy-selected');
    const toolbarBulk = $('#dca-open-empty-bulk');
    const toolbarExport = $('#dca-export-selected');
    const toolbarDeselect = $('#dca-deselect-selected');
    const toolbarImport = $('#dca-open-import');
    const toolbarRestore = $('#dca-restore-last-import');

    const requiredElements = [
        toast,
        singleModal,
        singleOut,
        singleTitle,
        singleStatus,
        singleView,
        singleSave,
        singleCopy,
        singleDownload,
        singleClose,
        bulkModal,
        bulkOut,
        bulkStatus,
        bulkPreview,
        bulkSave,
        bulkCheck,
        bulkCopy,
        bulkDownload,
        bulkClose,
        importModal,
        importFile,
        importStatus,
        importPreviewBox,
        importRun,
        importPreview,
        importClose,
        toolbarCopy,
        toolbarBulk,
        toolbarExport,
        toolbarDeselect,
        toolbarImport,
        toolbarRestore,
    ];

    if (!nonce || !ajaxUrl || requiredElements.some((element) => !element)) {
        console.warn('Content Sync Manager: admin UI niet volledig geladen. Herlaad de adminpagina.');
        return;
    }

    let currentPostId = null;
    let singleFilename = 'content-sync.txt';
    let bulkFilename = 'content-sync.txt';
    let importTxt = '';
    let importOk = false;
    let importPreviewHash = '';
    let bulkPreviewHash = '';
    let cache = {};
    let singleInitial = '';
    let bulkInitial = '';
    let bulkChecked = false;
    let toastTimer = null;
    let lastFocusedBeforeModal = null;

    function showToast(message) {
        toast.textContent = message;
        toast.classList.add('is-active');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-active'), 3500);
    }

    function focusable(modal) {
        return Array.from(modal.querySelectorAll('a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter((element) => element.offsetParent !== null);
    }

    function open(modal) {
        lastFocusedBeforeModal = document.activeElement;
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');

        const elements = focusable(modal);

        if (elements.length) {
            setTimeout(() => elements[0].focus(), 0);
        }
    }

    function close(modal) {
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');

        if (lastFocusedBeforeModal && typeof lastFocusedBeforeModal.focus === 'function') {
            lastFocusedBeforeModal.focus();
        }

        lastFocusedBeforeModal = null;
    }

    function status(element, message, type) {
        element.textContent = message || '';
        element.classList.remove('is-success', 'is-error');

        if (type) {
            element.classList.add(type);
        }
    }

    function dirty(type) {
        return (type === 'single' && singleOut.value !== singleInitial) || (type === 'bulk' && bulkOut.value !== bulkInitial);
    }

    function closeSafe(modal, type) {
        if (dirty(type) && !confirm('Je hebt wijzigingen die nog niet zijn opgeslagen. Toch sluiten?')) {
            return;
        }

        close(modal);
    }

    function ajax(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);

        Object.keys(data || {}).forEach((key) => {
            if (Array.isArray(data[key])) {
                data[key].forEach((value) => formData.append(key + '[]', value));
                return;
            }

            formData.append(key, data[key]);
        });

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: formData,
        }).then((response) => response.text().then((text) => {
            let parsed = null;

            try {
                parsed = text ? JSON.parse(text) : null;
            } catch (error) {
                const preview = String(text || '').replace(/\s+/g, ' ').trim().slice(0, 500);

                return {
                    success: false,
                    data: {
                        message: 'Server gaf geen geldige JSON terug. HTTP ' + response.status + '. Eerste response: ' + (preview || 'lege response'),
                    },
                };
            }

            if (!response.ok) {
                if (parsed && parsed.data && parsed.data.message) {
                    return parsed;
                }

                return {
                    success: false,
                    data: { message: 'AJAX-verzoek mislukt. HTTP ' + response.status + '.' },
                };
            }

            return parsed || {
                success: false,
                data: { message: 'Lege AJAX-response.' },
            };
        })).catch((error) => ({
            success: false,
            data: { message: 'AJAX-verzoek mislukt: ' + (error && error.message ? error.message : String(error)) },
        }));
    }

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        let copied = false;

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        textarea.remove();
        return copied;
    }

    function copy(text, element) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                status(element, 'Gekopieerd.', 'is-success');
            }).catch(() => {
                const copied = fallbackCopy(text);
                status(element, copied ? 'Gekopieerd.' : 'Kopiëren mislukt. Selecteer en kopieer handmatig.', copied ? 'is-success' : 'is-error');
            });
            return;
        }

        const copied = fallbackCopy(text);
        status(element, copied ? 'Gekopieerd.' : 'Kopiëren mislukt. Selecteer en kopieer handmatig.', copied ? 'is-success' : 'is-error');
    }

    function download(text, name) {
        const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = name || 'content-sync.txt';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
    }

    function selectedIds() {
        return $$('tbody th.check-column input[type="checkbox"][name="post[]"]:checked').map((checkbox) => checkbox.value);
    }

    function slug(value) {
        return String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function reloadList(message) {
        showToast((message || 'Opgeslagen') + '. Lijst wordt bijgewerkt...');
        setTimeout(() => {
            window.location.href = window.location.href;
        }, 900);
    }

    function updateSelectionToast() {
        showToast('Content Sync: ' + selectedIds().length + ' geselecteerd');
    }

    function deselectAll() {
        $$('tbody th.check-column input[type="checkbox"][name="post[]"], #cb-select-all-1, #cb-select-all-2').forEach((checkbox) => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
        });
        updateSelectionToast();
    }

    document.addEventListener('change', (event) => {
        if (event.target.matches('input[type="checkbox"][name="post[]"], #cb-select-all-1, #cb-select-all-2')) {
            setTimeout(updateSelectionToast, 40);
        }
    });

    toolbarDeselect.addEventListener('click', (event) => {
        event.preventDefault();
        deselectAll();
    });

    function saveBulkDraft() {
        try {
            if (bulkOut.value.trim()) {
                localStorage.setItem('dca_tb_bulk_draft', bulkOut.value);
            }
        } catch (error) {}
    }

    function clearBulkDraft() {
        try {
            localStorage.removeItem('dca_tb_bulk_draft');
        } catch (error) {}
    }

    function getBulkDraft() {
        try {
            return localStorage.getItem('dca_tb_bulk_draft') || '';
        } catch (error) {
            return '';
        }
    }

    function importableItems(items) {
        return Array.isArray(items) ? items.filter((item) => item && (item.status === 'success' || item.status === 'partial')) : [];
    }

    function previewSummary(items) {
        if (!Array.isArray(items) || !items.length) {
            return '';
        }

        const importable = importableItems(items).length;
        const blocked = items.length - importable;

        return importable + ' importeerbaar, ' + blocked + ' geblokkeerd.';
    }

    function setButtonEnabled(button, enabled) {
        button.disabled = !enabled;

        if (enabled) {
            button.removeAttribute('disabled');
            return;
        }

        button.setAttribute('disabled', 'disabled');
    }

    function appendHeaderCell(row, text) {
        const cell = document.createElement('th');
        cell.textContent = text;
        row.appendChild(cell);
    }

    function renderPreview(box, items) {
        clearElement(box);

        if (!Array.isArray(items)) {
            const message = document.createElement('p');
            message.className = 'dca-error';
            message.textContent = 'Geen geldige preview ontvangen.';
            box.appendChild(message);
            box.style.display = 'block';
            return;
        }

        const summary = previewSummary(items);

        if (summary) {
            const paragraph = document.createElement('p');
            const strong = document.createElement('strong');
            strong.textContent = 'Controle:';
            paragraph.appendChild(strong);
            paragraph.appendChild(document.createTextNode(' ' + summary));
            box.appendChild(paragraph);
        }

        const table = document.createElement('table');
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        appendHeaderCell(headerRow, 'Bron');
        appendHeaderCell(headerRow, 'Gekoppelde pagina');
        appendHeaderCell(headerRow, 'Status');
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        items.forEach((item) => {
            const rowItem = item || {};
            const row = document.createElement('tr');
            const sourceCell = document.createElement('td');
            const sourceTitle = document.createElement('strong');
            const targetCell = document.createElement('td');
            const statusCell = document.createElement('td');
            const statusClass = rowItem.status === 'success' ? 'dca-ok' : (rowItem.status === 'partial' ? 'dca-partial' : 'dca-error');
            const target = rowItem.target_title ? rowItem.target_title + ' (#' + (rowItem.target_post_id || 0) + ')' : 'Niet gevonden';

            sourceTitle.textContent = rowItem.source_title || 'Onbekend item';
            sourceCell.appendChild(sourceTitle);
            sourceCell.appendChild(document.createElement('br'));
            sourceCell.appendChild(document.createTextNode('ID: ' + (rowItem.source_id || '')));
            targetCell.textContent = target;
            statusCell.className = statusClass;
            statusCell.textContent = rowItem.message || 'Geen melding ontvangen.';

            row.appendChild(sourceCell);
            row.appendChild(targetCell);
            row.appendChild(statusCell);
            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        box.appendChild(table);
        box.style.display = 'block';
    }

    $$('.dca-open-acf-textblock').forEach((button) => button.addEventListener('click', function () {
        currentPostId = this.dataset.postId;
        status(singleStatus, '', '');
        singleFilename = 'content-sync-' + currentPostId + '.txt';

        const fill = (data) => {
            singleTitle.textContent = data.title;
            singleFilename = 'content-sync-' + slug(data.title) + '.txt';
            singleOut.value = data.text;
            singleInitial = data.text;
            singleView.href = data.view_url || '#';
            open(singleModal);
            singleOut.focus();
            singleOut.select();
        };

        if (cache[currentPostId]) {
            fill(cache[currentPostId]);
            return;
        }

        singleTitle.textContent = 'Content ophalen';
        singleOut.value = 'Tekst wordt opgehaald...';
        singleInitial = singleOut.value;
        open(singleModal);

        ajax('dca_get_acf_textblock', { post_id: currentPostId }).then((response) => {
            if (!response || !response.success) {
                singleOut.value = response && response.data && response.data.message ? response.data.message : 'Er ging iets mis.';
                return;
            }

            cache[currentPostId] = response.data;
            fill(response.data);
        }).catch(() => {
            singleOut.value = 'Er ging iets mis.';
        });
    }));

    singleSave.addEventListener('click', function () {
        if (!currentPostId) {
            status(singleStatus, 'Geen pagina geselecteerd.', 'is-error');
            return;
        }

        if (!confirm('Weet je zeker dat je dit contentblok wilt opslaan? Er wordt automatisch eerst een back-up gemaakt.')) {
            return;
        }

        this.disabled = true;
        status(singleStatus, 'Back-up maken en opslaan...', '');

        ajax('dca_save_acf_textblock', {
            post_id: currentPostId,
            textblock: singleOut.value,
            destructive_confirm: '1',
        }).then((response) => {
            this.disabled = false;

            if (!response || !response.success) {
                status(singleStatus, response && response.data && response.data.message ? response.data.message : 'Opslaan mislukt.', 'is-error');
                return;
            }

            singleInitial = singleOut.value;
            cache[currentPostId] = {
                title: singleTitle.textContent,
                text: singleOut.value,
                view_url: singleView.href,
            };
            status(singleStatus, response.data.message || 'Opgeslagen.', 'is-success');
            reloadList('Pagina opgeslagen');
        }).catch(() => {
            this.disabled = false;
            status(singleStatus, 'Opslaan mislukt.', 'is-error');
        });
    });

    singleCopy.addEventListener('click', () => {
        singleOut.focus();
        singleOut.select();
        copy(singleOut.value, singleStatus);
    });

    singleDownload.addEventListener('click', () => {
        download(singleOut.value, singleFilename);
        status(singleStatus, 'TXT-bestand gedownload.', 'is-success');
    });

    singleClose.addEventListener('click', () => closeSafe(singleModal, 'single'));

    function fetchBulk() {
        const ids = selectedIds();

        if (!ids.length) {
            showToast('Content Sync: 0 geselecteerd');
            alert('Selecteer eerst één of meerdere items.');
            return Promise.reject();
        }

        if (ids.length > 50 && !confirm('Je hebt ' + ids.length + ' pagina’s geselecteerd. Dit kan zwaar zijn voor de server. Toch doorgaan?')) {
            return Promise.reject();
        }

        return ajax('dca_bulk_get_acf_textblocks', { post_ids: ids });
    }

    function resetBulkCheck() {
        bulkChecked = false;
        bulkPreviewHash = '';
        setButtonEnabled(bulkSave, false);
        bulkPreview.style.display = 'none';
        clearElement(bulkPreview);
    }

    bulkOut.addEventListener('input', function () {
        resetBulkCheck();
        saveBulkDraft();
    });

    toolbarBulk.addEventListener('click', function () {
        const draft = getBulkDraft();

        bulkOut.value = '';
        bulkInitial = '';
        bulkFilename = 'content-sync-handmatig.txt';
        resetBulkCheck();

        if (draft && confirm('Er staat nog een lokaal concept van de bulkeditor. Wil je dit herstellen?')) {
            bulkOut.value = draft;
            bulkInitial = draft;
        }

        status(bulkStatus, 'Plak hier je bulktekst en klik daarna op “Controleer bulktekst”.', '');
        open(bulkModal);
        bulkOut.focus();
        showToast('Bulkeditor geopend. Plak je tekst en controleer vóór opslaan.');
    });

    toolbarCopy.addEventListener('click', function () {
        bulkOut.value = 'Contentblokken worden opgehaald...';
        bulkInitial = bulkOut.value;
        resetBulkCheck();
        status(bulkStatus, '', '');
        open(bulkModal);

        fetchBulk().then((response) => {
            if (!response || !response.success) {
                bulkOut.value = response && response.data && response.data.message ? response.data.message : 'Ophalen mislukt.';
                status(bulkStatus, 'Ophalen mislukt.', 'is-error');
                return;
            }

            bulkOut.value = response.data.text;
            bulkInitial = response.data.text;
            bulkFilename = response.data.filename || bulkFilename;
            saveBulkDraft();
            bulkOut.focus();
            bulkOut.select();
            copy(bulkOut.value, bulkStatus);
        }).catch(() => close(bulkModal));
    });

    toolbarExport.addEventListener('click', () => fetchBulk().then((response) => {
        if (!response || !response.success) {
            alert(response && response.data && response.data.message ? response.data.message : 'Exporteren mislukt.');
            return;
        }

        download(response.data.text, response.data.filename);
    }).catch(() => {}));

    bulkCheck.addEventListener('click', function () {
        if (!bulkOut.value.trim()) {
            status(bulkStatus, 'Er staat geen tekst om te controleren.', 'is-error');
            return;
        }

        status(bulkStatus, 'Controleren...', '');
        setButtonEnabled(bulkSave, false);
        bulkChecked = false;
        bulkPreviewHash = '';

        ajax('dca_txt_import_preview', { txt_content: bulkOut.value }).then((response) => {
            if (!response || !response.success) {
                bulkPreview.style.display = 'none';
                clearElement(bulkPreview);
                status(bulkStatus, response && response.data && response.data.message ? response.data.message : 'Controle mislukt.', 'is-error');
                return;
            }

            const items = Array.isArray(response.data && response.data.items) ? response.data.items : [];
            bulkPreviewHash = String((response.data && response.data.preview_hash) || '');
            renderPreview(bulkPreview, items);

            if (!items.length) {
                status(bulkStatus, 'Controle gaf geen items terug. Opslaan is geblokkeerd.', 'is-error');
                return;
            }

            const validItems = importableItems(items).length > 0;
            const hasErrors = items.some((item) => item.status !== 'success');
            bulkChecked = validItems && !!bulkPreviewHash;
            setButtonEnabled(bulkSave, bulkChecked);

            if (hasErrors) {
                status(bulkStatus, validItems ? 'Controle klaar: ' + previewSummary(items) + ' Geldige items kunnen worden opgeslagen; geblokkeerde items worden overgeslagen.' : 'Controle klaar: ' + previewSummary(items) + ' Er is niets om op te slaan. Bekijk de rode meldingen per item.', 'is-error');
                return;
            }

            status(bulkStatus, 'Controle geslaagd. ' + previewSummary(items) + ' Klaar om bulk op te slaan.', 'is-success');
        }).catch(() => status(bulkStatus, 'Controle mislukt.', 'is-error'));
    });

    bulkSave.addEventListener('click', function () {
        if (!bulkChecked || !bulkPreviewHash) {
            status(bulkStatus, 'Controleer eerst exact deze bulktekst opnieuw.', 'is-error');
            return;
        }

        if (!confirm('Weet je zeker dat je deze gecontroleerde bulk-tekst wilt opslaan? Geldige items kunnen bestaande content, ACF- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.')) {
            return;
        }

        this.disabled = true;
        status(bulkStatus, 'Back-ups maken en bulk opslaan...', '');

        ajax('dca_txt_import_run', {
            txt_content: bulkOut.value,
            preview_hash: bulkPreviewHash,
            destructive_confirm: '1',
        }).then((response) => {
            this.disabled = false;

            if (!response || !response.success) {
                status(bulkStatus, response && response.data && response.data.message ? response.data.message : 'Bulk opslaan mislukt.', 'is-error');
                return;
            }

            if (response.data && response.data.items) {
                renderPreview(bulkPreview, response.data.items);
            }

            bulkInitial = bulkOut.value;
            clearBulkDraft();
            status(bulkStatus, response.data.message || 'Bulk opgeslagen.', 'is-success');
            reloadList('Bulk opgeslagen');
        }).catch(() => {
            this.disabled = false;
            status(bulkStatus, 'Bulk opslaan mislukt.', 'is-error');
        });
    });

    bulkCopy.addEventListener('click', () => {
        bulkOut.focus();
        bulkOut.select();
        copy(bulkOut.value, bulkStatus);
    });

    bulkDownload.addEventListener('click', () => {
        download(bulkOut.value, bulkFilename);
        status(bulkStatus, 'TXT-bestand gedownload.', 'is-success');
    });

    bulkClose.addEventListener('click', () => closeSafe(bulkModal, 'bulk'));

    toolbarImport.addEventListener('click', () => {
        importTxt = '';
        importOk = false;
        importPreviewHash = '';
        importFile.value = '';
        clearElement(importPreviewBox);
        importPreviewBox.style.display = 'none';
        setButtonEnabled(importRun, false);
        status(importStatus, '', '');
        open(importModal);
    });

    toolbarRestore.addEventListener('click', function () {
        if (!confirm('Weet je zeker dat je de laatste import wilt terugzetten vanuit de automatische pagina-back-ups? Gebruik dit alleen direct na een foutieve import.')) {
            return;
        }

        this.disabled = true;
        showToast('Laatste import wordt hersteld...');

        ajax('dca_restore_last_import_pages', { destructive_confirm: '1' }).then((response) => {
            this.disabled = false;

            if (!response || !response.success) {
                showToast(response && response.data && response.data.message ? response.data.message : 'Herstellen mislukt.');
                return;
            }

            reloadList(response.data && response.data.message ? response.data.message : 'Laatste import hersteld');
        }).catch(() => {
            this.disabled = false;
            showToast('Herstellen mislukt.');
        });
    });

    importClose.addEventListener('click', () => close(importModal));

    function readFile() {
        return new Promise((resolve, reject) => {
            const file = importFile.files && importFile.files[0];

            if (!file) {
                reject('Kies eerst een TXT-bestand.');
                return;
            }

            if (!file.name.toLowerCase().endsWith('.txt')) {
                reject('Kies een geldig .txt-bestand.');
                return;
            }

            if (dcaSettings.maxImportBytes && file.size > Number(dcaSettings.maxImportBytes)) {
                reject('Bestand is te groot. Maximaal toegestaan: ' + Math.round(Number(dcaSettings.maxImportBytes) / 1024 / 1024) + ' MB.');
                return;
            }

            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject('Bestand kon niet gelezen worden.');
            reader.readAsText(file);
        });
    }

    importPreview.addEventListener('click', function () {
        importTxt = '';
        importOk = false;
        importPreviewHash = '';
        clearElement(importPreviewBox);
        importPreviewBox.style.display = 'none';
        setButtonEnabled(importRun, false);
        status(importStatus, 'Bestand lezen...', '');

        readFile().then((txt) => {
            importTxt = txt;
            status(importStatus, 'Bestand controleren...', '');
            return ajax('dca_txt_import_preview', { txt_content: txt });
        }).then((response) => {
            if (!response || !response.success) {
                importPreviewBox.style.display = 'none';
                clearElement(importPreviewBox);
                status(importStatus, response && response.data && response.data.message ? response.data.message : 'Controle mislukt.', 'is-error');
                return;
            }

            const items = Array.isArray(response.data && response.data.items) ? response.data.items : [];
            importPreviewHash = String((response.data && response.data.preview_hash) || '');
            renderPreview(importPreviewBox, items);

            if (!items.length) {
                status(importStatus, 'Controle gaf geen items terug. Importeren is geblokkeerd.', 'is-error');
                return;
            }

            const validItems = importableItems(items).length > 0;
            const hasErrors = items.some((item) => item.status !== 'success');
            importOk = validItems && !!importPreviewHash;
            setButtonEnabled(importRun, importOk);

            if (hasErrors) {
                status(importStatus, validItems ? 'Controle klaar: ' + previewSummary(items) + ' Geldige items kunnen worden geïmporteerd; geblokkeerde items worden overgeslagen.' : 'Controle klaar: ' + previewSummary(items) + ' Er is niets om te importeren. Bekijk de rode meldingen per item.', 'is-error');
                return;
            }

            status(importStatus, 'Controle geslaagd. ' + previewSummary(items) + ' Klaar om te importeren.', 'is-success');
        }).catch((message) => status(importStatus, message || 'Bestand kon niet gelezen worden.', 'is-error'));
    });

    importRun.addEventListener('click', function () {
        if (!importOk || !importTxt || !importPreviewHash) {
            status(importStatus, 'Controleer eerst exact dit bestand opnieuw.', 'is-error');
            return;
        }

        if (!confirm('Weet je zeker dat je dit gecontroleerde TXT-bestand wilt importeren? Geldige items kunnen bestaande content, ACF- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.')) {
            return;
        }

        this.disabled = true;
        status(importStatus, 'Back-ups maken en importeren...', '');

        ajax('dca_txt_import_run', {
            txt_content: importTxt,
            preview_hash: importPreviewHash,
            destructive_confirm: '1',
        }).then((response) => {
            if (!response || !response.success) {
                status(importStatus, response && response.data && response.data.message ? response.data.message : 'Import mislukt.', 'is-error');
                this.disabled = false;
                return;
            }

            if (response.data && response.data.items) {
                renderPreview(importPreviewBox, response.data.items);
            }

            status(importStatus, response.data.message || 'Import voltooid.', 'is-success');
            reloadList('Import voltooid');
        }).catch(() => {
            status(importStatus, 'Import mislukt.', 'is-error');
            this.disabled = false;
        });
    });

    [singleModal, bulkModal, importModal].forEach((modal) => modal.addEventListener('click', (event) => {
        if (event.target !== modal) {
            return;
        }

        if (modal === singleModal) {
            closeSafe(modal, 'single');
        } else if (modal === bulkModal) {
            closeSafe(modal, 'bulk');
        } else {
            close(modal);
        }
    }));

    document.addEventListener('keydown', (event) => {
        const activeModal = [singleModal, bulkModal, importModal].find((modal) => modal.classList.contains('is-active'));

        if (event.key === 'Escape') {
            if (singleModal.classList.contains('is-active')) {
                closeSafe(singleModal, 'single');
            } else if (bulkModal.classList.contains('is-active')) {
                closeSafe(bulkModal, 'bulk');
            } else if (importModal.classList.contains('is-active')) {
                close(importModal);
            }
        }

        if (event.key === 'Tab' && activeModal) {
            const elements = focusable(activeModal);

            if (!elements.length) {
                return;
            }

            const first = elements[0];
            const last = elements[elements.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty('single') || dirty('bulk')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});
