document.addEventListener('DOMContentLoaded', function () {
    const settings = window.dcaTbAiImageContextSettings || window.dcaTbSettings || {};
    const { __, sprintf } = window.wp.i18n;
    const nonce = settings.nonce;
    const ajaxUrl = window.ajaxurl || settings.ajaxUrl || '';
    const mediaScreen = settings.screen === 'media' || document.body.classList.contains('upload-php');

    if (!nonce || !ajaxUrl) {
        return;
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
        window.setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    function uniqueIds(ids) {
        return Array.from(new Set(ids.filter(function (id) {
            return Number.isInteger(id) && id > 0;
        })));
    }

    function mediaGridSelectionIds() {
        const ids = [];
        const wpMedia = window.wp && window.wp.media ? window.wp.media : null;
        const frame = wpMedia && wpMedia.frame ? wpMedia.frame : null;

        if (frame && typeof frame.state === 'function') {
            try {
                const state = frame.state();
                const selection = state && typeof state.get === 'function' ? state.get('selection') : null;
                if (selection && typeof selection.each === 'function') {
                    selection.each(function (model) {
                        const id = parseInt(model && model.id ? model.id : model.get('id'), 10);
                        if (Number.isInteger(id) && id > 0) {
                            ids.push(id);
                        }
                    });
                }
            } catch (error) {
                // WordPress can replace the media frame while switching modes; DOM selection remains the fallback.
            }
        }

        document.querySelectorAll('.attachments .attachment.selected[data-id]').forEach(function (attachment) {
            const id = parseInt(attachment.getAttribute('data-id'), 10);
            if (Number.isInteger(id) && id > 0) {
                ids.push(id);
            }
        });

        return uniqueIds(ids);
    }

    function selectedIds() {
        if (mediaScreen) {
            const listIds = Array.from(document.querySelectorAll('input[type="checkbox"][name="media[]"]:checked')).map(function (checkbox) {
                return parseInt(checkbox.value, 10);
            });
            const gridIds = mediaGridSelectionIds();
            return uniqueIds(listIds.concat(gridIds));
        }

        return uniqueIds(Array.from(document.querySelectorAll('input[type="checkbox"][name="post[]"]:checked')).map(function (checkbox) {
            return parseInt(checkbox.value, 10);
        }));
    }

    async function postAction(action, fields) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);
        Object.keys(fields || {}).forEach(function (key) {
            const value = fields[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) {
                    formData.append(key + '[]', String(item));
                });
                return;
            }
            formData.append(key, String(value));
        });

        const response = await fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        });
        const payload = await response.json();

        if (!response.ok || !payload || !payload.success || !payload.data) {
            const message = payload && payload.data && payload.data.message
                ? payload.data.message
                : __('De Content Sync-actie is mislukt.', 'content-sync-manager');
            throw new Error(message);
        }

        return payload.data;
    }

    function readTextFile(file) {
        if (file && typeof file.text === 'function') {
            return file.text();
        }

        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () {
                resolve(String(reader.result || ''));
            };
            reader.onerror = function () {
                reject(new Error(__('Het TXT-bestand kon niet worden gelezen.', 'content-sync-manager')));
            };
            reader.readAsText(file);
        });
    }

    function createControls(target, options) {
        if (!target || target.querySelector('[data-dca-ai-image-context-controls]')) {
            return null;
        }

        const opts = options || {};
        const wrapper = document.createElement('span');
        wrapper.dataset.dcaAiImageContextControls = '1';
        wrapper.className = opts.grid ? 'dca-ai-image-context-controls dca-ai-image-context-controls-grid media-button' : 'dca-ai-image-context-controls';

        const exportButton = document.createElement('button');
        exportButton.type = 'button';
        exportButton.className = opts.grid ? 'button media-button' : 'button';
        exportButton.dataset.dcaAiImageContext = '1';
        exportButton.textContent = __('AI afbeeldingen export', 'content-sync-manager');
        wrapper.appendChild(exportButton);

        const status = document.createElement('span');
        status.className = 'screen-reader-text';
        status.setAttribute('aria-live', 'polite');
        wrapper.appendChild(status);

        exportButton.addEventListener('click', async function () {
            const ids = selectedIds();
            if (!ids.length) {
                window.alert(mediaScreen
                    ? __('Selecteer eerst minimaal een afbeelding. Gebruik in de rasterweergave eerst Bulkselectie.', 'content-sync-manager')
                    : __('Selecteer eerst minimaal een pagina, bericht of product.', 'content-sync-manager'));
                return;
            }

            exportButton.disabled = true;
            status.textContent = __('AI-afbeeldingscontext wordt gemaakt.', 'content-sync-manager');

            try {
                const data = await postAction('dca_ai_image_context_export', {
                    scope: mediaScreen ? 'media' : 'content',
                    ids: ids,
                });
                if (!data.text) {
                    throw new Error(__('De export bevat geen tekst.', 'content-sync-manager'));
                }
                downloadText(data.text, data.filename);
                status.textContent = __('AI-afbeeldingscontext is geexporteerd.', 'content-sync-manager');
            } catch (error) {
                const message = error instanceof Error ? error.message : __('AI-afbeeldingscontext exporteren is mislukt.', 'content-sync-manager');
                window.alert(message);
                status.textContent = message;
            } finally {
                exportButton.disabled = false;
            }
        });

        if (mediaScreen) {
            const importButton = document.createElement('button');
            importButton.type = 'button';
            importButton.className = opts.grid ? 'button media-button' : 'button';
            importButton.dataset.dcaAiImageImport = '1';
            importButton.textContent = __('AI data importeren', 'content-sync-manager');

            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.txt,text/plain';
            fileInput.hidden = true;
            fileInput.dataset.dcaAiImageImportFile = '1';

            wrapper.appendChild(importButton);
            wrapper.appendChild(fileInput);

            importButton.addEventListener('click', function () {
                fileInput.value = '';
                fileInput.click();
            });

            fileInput.addEventListener('change', async function () {
                const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (!file) {
                    return;
                }

                importButton.disabled = true;
                exportButton.disabled = true;
                status.textContent = __('AI-media-import wordt gecontroleerd.', 'content-sync-manager');

                try {
                    const text = await readTextFile(file);
                    const preview = await postAction('dca_ai_image_context_import_preview', { text: text });
                    const importable = parseInt(preview.importable || 0, 10);
                    const changes = parseInt(preview.changes || 0, 10);
                    const errors = parseInt(preview.errors || 0, 10);
                    const renameBlocked = parseInt(preview.rename_blocked || 0, 10);

                    if (!importable) {
                        throw new Error(sprintf(__('Geen geldige media-items gevonden om te importeren. Fouten: %d.', 'content-sync-manager'), errors));
                    }

                    const confirmed = window.confirm(sprintf(
                        __('Controle voltooid. Importabele afbeeldingen: %1$d. Wijzigingen: %2$d. Fouten: %3$d. Veilig geblokkeerde hernoemingen: %4$d. Doorgaan met importeren?', 'content-sync-manager'),
                        importable,
                        changes,
                        errors,
                        renameBlocked
                    ));

                    if (!confirmed) {
                        status.textContent = __('AI-media-import geannuleerd.', 'content-sync-manager');
                        return;
                    }

                    const result = await postAction('dca_ai_image_context_import_run', {
                        text: text,
                        preview_hash: preview.preview_hash,
                        destructive_confirm: '1',
                    });
                    window.alert(result.message || __('AI-media-import is voltooid.', 'content-sync-manager'));
                    status.textContent = __('AI-media-import is voltooid.', 'content-sync-manager');
                    window.location.reload();
                } catch (error) {
                    const message = error instanceof Error ? error.message : __('AI-media-import is mislukt.', 'content-sync-manager');
                    window.alert(message);
                    status.textContent = message;
                } finally {
                    importButton.disabled = false;
                    exportButton.disabled = false;
                }
            });
        }

        target.appendChild(wrapper);
        return wrapper;
    }

    function ensureMediaControls() {
        const gridBulkSelect = document.querySelector('#wp-media-grid .media-frame.mode-grid .media-toolbar .select-mode-toggle-button')
            || document.querySelector('.media-frame.mode-grid .media-toolbar .select-mode-toggle-button')
            || document.querySelector('.media-frame.mode-grid .media-toolbar .bulk-select');
        if (gridBulkSelect && gridBulkSelect.parentElement) {
            const parent = gridBulkSelect.parentElement;
            if (!parent.querySelector('[data-dca-ai-image-context-controls]')) {
                const controls = createControls(parent, { grid: true });
                if (controls) {
                    gridBulkSelect.insertAdjacentElement('afterend', controls);
                }
            }
            return;
        }

        const listTarget = document.querySelector('.tablenav.top .actions.bulkactions')
            || document.querySelector('.tablenav.top .actions');
        if (listTarget) {
            createControls(listTarget, { grid: false });
        }
    }

    if (mediaScreen) {
        ensureMediaControls();
        let scheduled = false;
        const observer = new MutationObserver(function () {
            if (scheduled) {
                return;
            }
            scheduled = true;
            window.requestAnimationFrame(function () {
                scheduled = false;
                ensureMediaControls();
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
        return;
    }

    const toolbar = document.querySelector('.dca-toolbar, .dca-tb-toolbar, [data-dca-toolbar]');
    createControls(toolbar, { grid: false });
});
