document.addEventListener('DOMContentLoaded', function () {
    const dcaSettings = window.dcaTbSettings || {};
    const { __, sprintf } = window.wp.i18n;
    const nonce = dcaSettings.nonce;
    const filterUrl = dcaSettings.filterUrl;
    const filterLabel = dcaSettings.filterLabel;
    const ajaxUrl = window.ajaxurl || dcaSettings.ajaxUrl || '';
    const objectType = dcaSettings.objectType || 'post';
    const taxonomy = dcaSettings.taxonomy || '';
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
            ['dca-copy-selected', __('Kopieer selectie', 'content-sync-manager'), 'button'],
            ['dca-export-selected', __('Export selectie .txt', 'content-sync-manager'), 'button'],
            ['dca-open-empty-bulk', __('Bulkeditor', 'content-sync-manager'), 'button'],
            ['dca-select-all', __('Selecteer alles', 'content-sync-manager'), 'button'],
            ['dca-deselect-selected', __('Deselecteer alles', 'content-sync-manager'), 'button'],
            ['dca-open-import', __('Import .txt', 'content-sync-manager'), 'button button-primary'],
            ['dca-restore-last-import', __('Herstel laatste import', 'content-sync-manager'), 'button'],
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

        if (filterUrl) {
            const link = document.createElement('a');
            link.className = 'button dca-toolbar-filter';
            link.href = filterUrl;
            link.textContent = filterLabel || __('Filter', 'content-sync-manager');
            bar.appendChild(link);
        }

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
    const toolbarSelectAll = $('#dca-select-all');
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
        toolbarSelectAll,
        toolbarDeselect,
        toolbarImport,
        toolbarRestore,
    ];

    if (!nonce || !ajaxUrl || requiredElements.some((element) => !element)) {
        console.warn(__('Content Sync Manager: admin UI niet volledig geladen. Herlaad de adminpagina.', 'content-sync-manager'));
        return;
    }

    let currentObject = null;
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
        if (dirty(type) && !confirm(__('Je hebt wijzigingen die nog niet zijn opgeslagen. Toch sluiten?', 'content-sync-manager'))) {
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
                        message: __('Server gaf geen geldige JSON terug. HTTP ', 'content-sync-manager') + response.status + '. Eerste response: ' + (preview || __('lege response', 'content-sync-manager')),
                    },
                };
            }

            if (!response.ok) {
                if (parsed && parsed.data && parsed.data.message) {
                    return parsed;
                }

                return {
                    success: false,
                    data: { message: __('AJAX-verzoek mislukt. HTTP ', 'content-sync-manager') + response.status + '.' },
                };
            }

            return parsed || {
                success: false,
                data: { message: __('Lege AJAX-response.', 'content-sync-manager') },
            };
        })).catch((error) => ({
            success: false,
            data: { message: __('AJAX-verzoek mislukt: ', 'content-sync-manager') + (error && error.message ? error.message : String(error)) },
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
                status(element, __('Gekopieerd.', 'content-sync-manager'), 'is-success');
            }).catch(() => {
                const copied = fallbackCopy(text);
                status(element, copied ? __('Gekopieerd.', 'content-sync-manager') : __('Kopiëren mislukt. Selecteer en kopieer handmatig.', 'content-sync-manager'), copied ? 'is-success' : 'is-error');
            });
            return;
        }

        const copied = fallbackCopy(text);
        status(element, copied ? __('Gekopieerd.', 'content-sync-manager') : __('Kopiëren mislukt. Selecteer en kopieer handmatig.', 'content-sync-manager'), copied ? 'is-success' : 'is-error');
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

    function rowCheckboxSelector() {
        return objectType === 'term'
            ? 'input[type="checkbox"][name="delete_tags[]"]'
            : 'input[type="checkbox"][name="post[]"]';
    }

    function selectedIds() {
        return $$(rowCheckboxSelector() + ':checked').map((checkbox) => checkbox.value);
    }

    function slug(value) {
        return String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function reloadList(message) {
        showToast((message || __('Opgeslagen', 'content-sync-manager')) + __('. Lijst wordt bijgewerkt...', 'content-sync-manager'));
        setTimeout(() => {
            window.location.href = window.location.href;
        }, 900);
    }

    function updateSelectionToast() {
        showToast(sprintf(__('Content Sync: %d geselecteerd', 'content-sync-manager'), selectedIds().length));
    }

    function selectAll() {
        const rowCheckboxes = $$(rowCheckboxSelector());

        rowCheckboxes.forEach((checkbox) => {
            checkbox.checked = true;
            checkbox.indeterminate = false;
        });

        $$('#cb-select-all-1, #cb-select-all-2').forEach((checkbox) => {
            checkbox.checked = rowCheckboxes.length > 0;
            checkbox.indeterminate = false;
        });

        updateSelectionToast();
    }

    function deselectAll() {
        $$(rowCheckboxSelector() + ', #cb-select-all-1, #cb-select-all-2').forEach((checkbox) => {
            checkbox.checked = false;
            checkbox.indeterminate = false;
        });
        updateSelectionToast();
    }

    document.addEventListener('change', (event) => {
        if (event.target.matches(rowCheckboxSelector() + ', #cb-select-all-1, #cb-select-all-2')) {
            setTimeout(updateSelectionToast, 40);
        }
    });

    toolbarSelectAll.addEventListener('click', (event) => {
        event.preventDefault();
        selectAll();
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

        return sprintf(__('%1$d importeerbaar, %2$d geblokkeerd.', 'content-sync-manager'), importable, blocked);
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
            message.textContent = __('Geen geldige preview ontvangen.', 'content-sync-manager');
            box.appendChild(message);
            box.style.display = 'block';
            return;
        }

        const summary = previewSummary(items);

        if (summary) {
            const paragraph = document.createElement('p');
            const strong = document.createElement('strong');
            strong.textContent = __('Controle:', 'content-sync-manager');
            paragraph.appendChild(strong);
            paragraph.appendChild(document.createTextNode(' ' + summary));
            box.appendChild(paragraph);
        }

        const table = document.createElement('table');
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        appendHeaderCell(headerRow, __('Bron', 'content-sync-manager'));
        appendHeaderCell(headerRow, __('Gekoppelde pagina', 'content-sync-manager'));
        appendHeaderCell(headerRow, __('Status', 'content-sync-manager'));
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
            const targetId = rowItem.target_id || rowItem.target_post_id || 0;
            const target = rowItem.target_title ? sprintf(__('%1$s (#%2$d)', 'content-sync-manager'), rowItem.target_title, targetId) : __('Niet gevonden', 'content-sync-manager');

            sourceTitle.textContent = rowItem.source_title || __('Onbekend item', 'content-sync-manager');
            sourceCell.appendChild(sourceTitle);
            sourceCell.appendChild(document.createElement('br'));
            sourceCell.appendChild(document.createTextNode(sprintf(__('ID: %s', 'content-sync-manager'), rowItem.source_id || '')));
            targetCell.textContent = target;
            statusCell.className = statusClass;
            statusCell.textContent = rowItem.message || __('Geen melding ontvangen.', 'content-sync-manager');

            row.appendChild(sourceCell);
            row.appendChild(targetCell);
            row.appendChild(statusCell);
            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        box.appendChild(table);
        box.style.display = 'block';
    }

    function currentObjectKey() {
        if (!currentObject) {
            return '';
        }

        return [currentObject.type, currentObject.taxonomy || '', currentObject.id].join(':');
    }

    function currentObjectPayload() {
        if (!currentObject) {
            return {};
        }

        if (currentObject.type === 'term') {
            return {
                object_type: 'term',
                term_id: currentObject.id,
                taxonomy: currentObject.taxonomy,
            };
        }

        return {
            object_type: 'post',
            post_id: currentObject.id,
        };
    }

    $$('.dca-open-acf-textblock').forEach((button) => button.addEventListener('click', function () {
        const buttonObjectType = this.dataset.objectType === 'term' ? 'term' : 'post';
        currentObject = buttonObjectType === 'term'
            ? { type: 'term', id: this.dataset.termId, taxonomy: this.dataset.taxonomy || taxonomy }
            : { type: 'post', id: this.dataset.postId, taxonomy: '' };

        const cacheKey = currentObjectKey();
        status(singleStatus, '', '');
        singleFilename = 'content-sync-' + currentObject.id + '.txt';

        const fill = (data) => {
            singleTitle.textContent = data.title;
            singleFilename = 'content-sync-' + slug(data.title) + '.txt';
            singleOut.value = data.text;
            singleInitial = data.text;
            singleView.href = data.view_url || '#';
            singleView.style.display = data.view_url ? '' : 'none';
            open(singleModal);
            singleOut.focus();
            singleOut.select();
        };

        if (cache[cacheKey]) {
            fill(cache[cacheKey]);
            return;
        }

        singleTitle.textContent = __('Content ophalen', 'content-sync-manager');
        singleOut.value = __('Tekst wordt opgehaald...', 'content-sync-manager');
        singleInitial = singleOut.value;
        open(singleModal);

        ajax('dca_get_acf_textblock', currentObjectPayload()).then((response) => {
            if (!response || !response.success) {
                singleOut.value = response && response.data && response.data.message ? response.data.message : __('Er ging iets mis.', 'content-sync-manager');
                return;
            }

            cache[cacheKey] = response.data;
            fill(response.data);
        }).catch(() => {
            singleOut.value = __('Er ging iets mis.', 'content-sync-manager');
        });
    }));

    singleSave.addEventListener('click', function () {
        if (!currentObject) {
            status(singleStatus, __('Geen item geselecteerd.', 'content-sync-manager'), 'is-error');
            return;
        }

        if (!confirm(__('Weet je zeker dat je dit contentblok wilt opslaan? Er wordt automatisch eerst een back-up gemaakt.', 'content-sync-manager'))) {
            return;
        }

        this.disabled = true;
        status(singleStatus, __('Back-up maken en opslaan...', 'content-sync-manager'), '');

        ajax('dca_save_acf_textblock', Object.assign(currentObjectPayload(), {
            textblock: singleOut.value,
            destructive_confirm: '1',
        })).then((response) => {
            this.disabled = false;

            if (!response || !response.success) {
                status(singleStatus, response && response.data && response.data.message ? response.data.message : __('Opslaan mislukt.', 'content-sync-manager'), 'is-error');
                return;
            }

            singleInitial = singleOut.value;
            cache[currentObjectKey()] = {
                title: singleTitle.textContent,
                text: singleOut.value,
                view_url: singleView.href,
            };
            status(singleStatus, response.data.message || __('Opgeslagen.', 'content-sync-manager'), 'is-success');
            reloadList(currentObject.type === 'term' ? __('Categorie opgeslagen', 'content-sync-manager') : __('Pagina opgeslagen', 'content-sync-manager'));
        }).catch(() => {
            this.disabled = false;
            status(singleStatus, __('Opslaan mislukt.', 'content-sync-manager'), 'is-error');
        });
    });

    singleCopy.addEventListener('click', () => {
        singleOut.focus();
        singleOut.select();
        copy(singleOut.value, singleStatus);
    });

    singleDownload.addEventListener('click', () => {
        download(singleOut.value, singleFilename);
        status(singleStatus, __('TXT-bestand gedownload.', 'content-sync-manager'), 'is-success');
    });

    singleClose.addEventListener('click', () => closeSafe(singleModal, 'single'));

    function fetchBulk() {
        const ids = selectedIds();

        if (!ids.length) {
            showToast(__('Content Sync: 0 geselecteerd', 'content-sync-manager'));
            alert(__('Selecteer eerst één of meerdere items.', 'content-sync-manager'));
            return Promise.reject();
        }

        if (ids.length > 50 && !confirm(sprintf(__('Je hebt %d items geselecteerd. Dit kan zwaar zijn voor de server. Toch doorgaan?', 'content-sync-manager'), ids.length))) {
            return Promise.reject();
        }

        return ajax('dca_bulk_get_acf_textblocks', {
            object_type: objectType,
            taxonomy,
            object_ids: ids,
            post_ids: ids,
        });
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

        if (draft && confirm(__('Er staat nog een lokaal concept van de bulkeditor. Wil je dit herstellen?', 'content-sync-manager'))) {
            bulkOut.value = draft;
            bulkInitial = draft;
        }

        status(bulkStatus, __('Plak hier je bulktekst en klik daarna op “Controleer bulktekst”.', 'content-sync-manager'), '');
        open(bulkModal);
        bulkOut.focus();
        showToast(__('Bulkeditor geopend. Plak je tekst en controleer vóór opslaan.', 'content-sync-manager'));
    });

    toolbarCopy.addEventListener('click', function () {
        bulkOut.value = __('Contentblokken worden opgehaald...', 'content-sync-manager');
        bulkInitial = bulkOut.value;
        resetBulkCheck();
        status(bulkStatus, '', '');
        open(bulkModal);

        fetchBulk().then((response) => {
            if (!response || !response.success) {
                bulkOut.value = response && response.data && response.data.message ? response.data.message : __('Ophalen mislukt.', 'content-sync-manager');
                status(bulkStatus, __('Ophalen mislukt.', 'content-sync-manager'), 'is-error');
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
            alert(response && response.data && response.data.message ? response.data.message : __('Exporteren mislukt.', 'content-sync-manager'));
            return;
        }

        download(response.data.text, response.data.filename);
    }).catch(() => {}));

    bulkCheck.addEventListener('click', function () {
        if (!bulkOut.value.trim()) {
            status(bulkStatus, __('Er staat geen tekst om te controleren.', 'content-sync-manager'), 'is-error');
            return;
        }

        status(bulkStatus, __('Controleren...', 'content-sync-manager'), '');
        setButtonEnabled(bulkSave, false);
        bulkChecked = false;
        bulkPreviewHash = '';

        ajax('dca_txt_import_preview', { txt_content: bulkOut.value }).then((response) => {
            if (!response || !response.success) {
                bulkPreview.style.display = 'none';
                clearElement(bulkPreview);
                status(bulkStatus, response && response.data && response.data.message ? response.data.message : __('Controle mislukt.', 'content-sync-manager'), 'is-error');
                return;
            }

            const items = Array.isArray(response.data && response.data.items) ? response.data.items : [];
            bulkPreviewHash = String((response.data && response.data.preview_hash) || '');
            renderPreview(bulkPreview, items);

            if (!items.length) {
                status(bulkStatus, __('Controle gaf geen items terug. Opslaan is geblokkeerd.', 'content-sync-manager'), 'is-error');
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
        }).catch(() => status(bulkStatus, __('Controle mislukt.', 'content-sync-manager'), 'is-error'));
    });

    bulkSave.addEventListener('click', function () {
        if (!bulkChecked || !bulkPreviewHash) {
            status(bulkStatus, __('Controleer eerst exact deze bulktekst opnieuw.', 'content-sync-manager'), 'is-error');
            return;
        }

        if (!confirm(__('Weet je zeker dat je deze gecontroleerde bulk-tekst wilt opslaan? Geldige items kunnen bestaande content, ACF-, SEO-, categorie- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.', 'content-sync-manager'))) {
            return;
        }

        this.disabled = true;
        status(bulkStatus, __('Back-ups maken en bulk opslaan...', 'content-sync-manager'), '');

        ajax('dca_txt_import_run', {
            txt_content: bulkOut.value,
            preview_hash: bulkPreviewHash,
            destructive_confirm: '1',
        }).then((response) => {
            this.disabled = false;

            if (!response || !response.success) {
                status(bulkStatus, response && response.data && response.data.message ? response.data.message : __('Bulk opslaan mislukt.', 'content-sync-manager'), 'is-error');
                return;
            }

            if (response.data && response.data.items) {
                renderPreview(bulkPreview, response.data.items);
            }

            bulkInitial = bulkOut.value;
            clearBulkDraft();
            status(bulkStatus, response.data.message || __('Bulk opgeslagen.', 'content-sync-manager'), 'is-success');
            reloadList(__('Bulk opgeslagen', 'content-sync-manager'));
        }).catch(() => {
            this.disabled = false;
            status(bulkStatus, __('Bulk opslaan mislukt.', 'content-sync-manager'), 'is-error');
        });
    });

    bulkCopy.addEventListener('click', () => {
        bulkOut.focus();
        bulkOut.select();
        copy(bulkOut.value, bulkStatus);
    });

    bulkDownload.addEventListener('click', () => {
        download(bulkOut.value, bulkFilename);
        status(bulkStatus, __('TXT-bestand gedownload.', 'content-sync-manager'), 'is-success');
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
        if (!confirm(__('Weet je zeker dat je de laatste import wilt terugzetten vanuit de automatische pagina-back-ups? Gebruik dit alleen direct na een foutieve import.', 'content-sync-manager'))) {
            return;
        }

        this.disabled = true;
        showToast(__('Laatste import wordt hersteld...', 'content-sync-manager'));

        ajax('dca_restore_last_import_pages', { destructive_confirm: '1' }).then((response) => {
            this.disabled = false;

            if (!response || !response.success) {
                showToast(response && response.data && response.data.message ? response.data.message : __('Herstellen mislukt.', 'content-sync-manager'));
                return;
            }

            reloadList(response.data && response.data.message ? response.data.message : __('Laatste import hersteld', 'content-sync-manager'));
        }).catch(() => {
            this.disabled = false;
            showToast(__('Herstellen mislukt.', 'content-sync-manager'));
        });
    });

    importClose.addEventListener('click', () => close(importModal));

    function readFile() {
        return new Promise((resolve, reject) => {
            const file = importFile.files && importFile.files[0];

            if (!file) {
                reject(__('Kies eerst een TXT-bestand.', 'content-sync-manager'));
                return;
            }

            if (!file.name.toLowerCase().endsWith('.txt')) {
                reject(__('Kies een geldig .txt-bestand.', 'content-sync-manager'));
                return;
            }

            if (dcaSettings.maxImportBytes && file.size > Number(dcaSettings.maxImportBytes)) {
                reject(sprintf(__('Bestand is te groot. Maximaal toegestaan: %d MB.', 'content-sync-manager'), Math.round(Number(dcaSettings.maxImportBytes) / 1024 / 1024)));
                return;
            }

            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject(__('Bestand kon niet gelezen worden.', 'content-sync-manager'));
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
        status(importStatus, __('Bestand lezen...', 'content-sync-manager'), '');

        readFile().then((txt) => {
            importTxt = txt;
            status(importStatus, __('Bestand controleren...', 'content-sync-manager'), '');
            return ajax('dca_txt_import_preview', { txt_content: txt });
        }).then((response) => {
            if (!response || !response.success) {
                importPreviewBox.style.display = 'none';
                clearElement(importPreviewBox);
                status(importStatus, response && response.data && response.data.message ? response.data.message : __('Controle mislukt.', 'content-sync-manager'), 'is-error');
                return;
            }

            const items = Array.isArray(response.data && response.data.items) ? response.data.items : [];
            importPreviewHash = String((response.data && response.data.preview_hash) || '');
            renderPreview(importPreviewBox, items);

            if (!items.length) {
                status(importStatus, __('Controle gaf geen items terug. Importeren is geblokkeerd.', 'content-sync-manager'), 'is-error');
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
        }).catch((message) => status(importStatus, message || __('Bestand kon niet gelezen worden.', 'content-sync-manager'), 'is-error'));
    });

    importRun.addEventListener('click', function () {
        if (!importOk || !importTxt || !importPreviewHash) {
            status(importStatus, __('Controleer eerst exact dit bestand opnieuw.', 'content-sync-manager'), 'is-error');
            return;
        }

        if (!confirm(__('Weet je zeker dat je dit gecontroleerde TXT-bestand wilt importeren? Geldige items kunnen bestaande content, ACF-, SEO-, categorie- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.', 'content-sync-manager'))) {
            return;
        }

        this.disabled = true;
        status(importStatus, __('Back-ups maken en importeren...', 'content-sync-manager'), '');

        ajax('dca_txt_import_run', {
            txt_content: importTxt,
            preview_hash: importPreviewHash,
            destructive_confirm: '1',
        }).then((response) => {
            if (!response || !response.success) {
                status(importStatus, response && response.data && response.data.message ? response.data.message : __('Import mislukt.', 'content-sync-manager'), 'is-error');
                this.disabled = false;
                return;
            }

            if (response.data && response.data.items) {
                renderPreview(importPreviewBox, response.data.items);
            }

            status(importStatus, response.data.message || __('Import voltooid.', 'content-sync-manager'), 'is-success');
            reloadList(__('Import voltooid', 'content-sync-manager'));
        }).catch(() => {
            status(importStatus, __('Import mislukt.', 'content-sync-manager'), 'is-error');
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
