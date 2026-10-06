// Admin panel behaviour: slug auto-suggestion + the block editor used on
// page-edit.php. Both are guarded so this file can be loaded on every
// admin page without erroring where the relevant elements don't exist.

(function initSlugSuggestion() {
    var titleField = document.getElementById('title');
    var slugField = document.getElementById('slug');
    if (!titleField || !slugField) return;

    var slugManuallyEdited = slugField.value.trim() !== '';
    slugField.addEventListener('input', function () { slugManuallyEdited = true; });
    titleField.addEventListener('input', function () {
        if (slugManuallyEdited) return;
        slugField.value = titleField.value
            .toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
    });
})();

(function initMetaDescriptionHints() {
    var fields = document.querySelectorAll('[data-meta-description-input]');
    fields.forEach(function (field) {
        var hint = field.nextElementSibling;
        if (!hint || !hint.hasAttribute('data-meta-description-hint')) return;

        function update() {
            var len = field.value.length;
            if (len === 0) {
                hint.textContent = 'Geen meta-omschrijving ingevuld — dit wordt aanbevolen voor SEO.';
                hint.classList.add('field-hint-warn');
            } else if (len > 160) {
                hint.textContent = len + ' tekens — zoekmachines kappen de omschrijving vaak af na ongeveer 160 tekens.';
                hint.classList.add('field-hint-warn');
            } else {
                hint.textContent = len + ' tekens (ideaal: 50–160).';
                hint.classList.remove('field-hint-warn');
            }
        }

        field.addEventListener('input', update);
        update();
    });
})();

(function initBlockEditor() {
    var container = document.getElementById('block-editor');
    var hiddenInput = document.getElementById('blocks_json');
    var initialData = document.getElementById('initial-blocks');
    var form = document.querySelector('.page-form');
    if (!container || !hiddenInput || !form) return;

    var BLOCK_TYPES = [
        { type: 'text', label: 'Tekst' },
        { type: 'image', label: 'Foto' },
        { type: 'quote', label: 'Quote' },
        { type: 'list', label: 'Lijst' },
        { type: 'buttons', label: 'Knoppen' },
        { type: 'calendar', label: 'Kalender' },
        { type: 'events', label: 'Evenementen' },
        { type: 'map', label: 'Kaart' },
        { type: 'media_text', label: 'Foto + tekst' },
        { type: 'columns', label: 'Kolommen' }
    ];
    var LABELS = BLOCK_TYPES.reduce(function (acc, t) { acc[t.type] = t.label; return acc; }, {});

    // Restricts which block types the top-level "+ ..." toolbar offers —
    // used by the newsletter editor (data-block-types="text,image,quote,
    // list,buttons" on #block-editor) to only offer types that actually
    // render in an e-mail client, see NEWSLETTER_BLOCK_TYPES in
    // functions.php. LABELS stays unfiltered so any already-saved content
    // still displays its proper label regardless.
    var allowedTypesAttr = container.getAttribute('data-block-types');
    var TOOLBAR_BLOCK_TYPES = allowedTypesAttr
        ? BLOCK_TYPES.filter(function (t) { return allowedTypesAttr.split(',').indexOf(t.type) !== -1; })
        : BLOCK_TYPES;

    // Block types a columns-block's column may contain — mirrors
    // COLUMN_CHILD_TYPES in includes/functions.php. No calendar/events/
    // map/media_text/columns, to avoid runaway nesting.
    var COLUMN_TYPES = ['text', 'image', 'quote', 'list', 'buttons'];
    var COLUMN_BLOCK_TYPES = BLOCK_TYPES.filter(function (t) { return COLUMN_TYPES.indexOf(t.type) !== -1; });

    function newBlock(type) {
        switch (type) {
            case 'text': return { type: 'text', eyebrow: '', heading: '', body: '', background: 'none' };
            case 'image': return { type: 'image', url: '', alt: '', caption: '', background: 'none' };
            case 'quote': return { type: 'quote', text: '', source: '', background: 'none' };
            case 'list': return { type: 'list', heading: '', style: 'bullet', itemsText: '', background: 'none' };
            case 'buttons': return { type: 'buttons', buttonsText: '', align: 'left', background: 'none' };
            case 'calendar': return { type: 'calendar', heading: '', background: 'none' };
            case 'events': return { type: 'events', heading: '', background: 'none' };
            case 'map': return { type: 'map', address: '', heading: '', layout: 'box', background: 'none' };
            case 'media_text': return { type: 'media_text', url: '', alt: '', caption: '', heading: '', body: '', image_position: 'left', background: 'none' };
            case 'columns': return { type: 'columns', column_count: 2, columns: [[], []], column_backgrounds: ['none', 'none'], background: 'none' };
            default: return null;
        }
    }

    // Canonical (stored/rendered) shape <-> editor shape. list/buttons use
    // one plain textarea in the editor instead of a repeating field group;
    // columns recurses into its per-column child arrays with the same
    // per-block conversion, since a column can hold list/buttons blocks too.
    function toEditorBlock(b) {
        if (b.type === 'list') {
            return { type: 'list', heading: b.heading || '', style: b.style || 'bullet', itemsText: (b.items || []).join('\n'), background: b.background || 'none' };
        }
        if (b.type === 'buttons') {
            var lines = (b.buttons || []).map(function (btn) { return (btn.label || '') + ' | ' + (btn.url || ''); });
            return { type: 'buttons', buttonsText: lines.join('\n'), align: b.align || 'left', background: b.background || 'none' };
        }
        if (b.type === 'columns') {
            return {
                type: 'columns',
                column_count: b.column_count || 2,
                columns: (b.columns || []).map(function (children) { return (children || []).map(toEditorBlock); }),
                column_backgrounds: (b.column_backgrounds || []).slice(),
                background: b.background || 'none'
            };
        }
        return Object.assign({}, b);
    }

    function toCanonicalBlock(b) {
        if (b.type === 'list') {
            var items = (b.itemsText || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 20);
            return { type: 'list', heading: b.heading || '', style: b.style || 'bullet', items: items, background: b.background || 'none' };
        }
        if (b.type === 'buttons') {
            var buttons = (b.buttonsText || '').split('\n').map(function (line) {
                var parts = line.split('|');
                return { label: (parts[0] || '').trim(), url: (parts.slice(1).join('|') || '').trim() };
            }).filter(function (btn) { return btn.label && btn.url; }).slice(0, 3);
            return { type: 'buttons', buttons: buttons, align: b.align || 'left', background: b.background || 'none' };
        }
        if (b.type === 'columns') {
            return {
                type: 'columns',
                column_count: b.column_count || 2,
                columns: (b.columns || []).map(function (children) { return (children || []).map(toCanonicalBlock); }),
                column_backgrounds: (b.column_backgrounds || []).slice(),
                background: b.background || 'none'
            };
        }
        return Object.assign({}, b);
    }

    function toEditorShape(blocks) { return blocks.map(toEditorBlock); }
    function toCanonicalShape(blocks) { return blocks.map(toCanonicalBlock); }

    var blocks = [];
    try {
        var raw = initialData ? JSON.parse(initialData.textContent || '[]') : [];
        blocks = toEditorShape(Array.isArray(raw) ? raw : []);
    } catch (e) {
        blocks = [];
    }

    // Resolves a "path" string to the array+index it refers to, so every
    // handler below works the same whether a block-card is top-level
    // ("2") or nested inside a columns-block's column ("2:0:1" = block 2,
    // column 0, child 1). listId identifies the array for drag-and-drop
    // (only same-list drags are allowed — see dragover/drop below).
    function resolvePath(path) {
        var parts = String(path).split(':').map(function (n) { return parseInt(n, 10); });
        if (parts.length === 1) {
            return { array: blocks, index: parts[0], listId: 'root' };
        }
        return { array: blocks[parts[0]].columns[parts[1]], index: parts[2], listId: parts[0] + ':' + parts[1] };
    }

    function swapInArray(arr, i, j) {
        var tmp = arr[i];
        arr[i] = arr[j];
        arr[j] = tmp;
    }

    function fieldRow(labelText, inputHtml) {
        return '<label>' + labelText + '</label>' + inputHtml;
    }

    function backgroundFieldRow(value) {
        return fieldRow('Achtergrond', (
            '<select data-field="background">' +
                '<option value="none"' + (value !== 'accent' && value !== 'surface' ? ' selected' : '') + '>Geen</option>' +
                '<option value="accent"' + (value === 'accent' ? ' selected' : '') + '>Accentkleur</option>' +
                '<option value="surface"' + (value === 'surface' ? ' selected' : '') + '>Zachte kaart</option>' +
            '</select>'
        ));
    }

    // A small toolbar that wraps the current textarea selection in simple,
    // safe markup (**vet**, *cursief*, [label](url)) instead of storing
    // real HTML — render_inline_markup() in functions.php turns it into
    // tags server-side, after escaping, so nothing typed here can ever
    // become live HTML on its own.
    function richTextToolbar(targetField, includeList) {
        return (
            '<div class="richtext-toolbar">' +
                '<button type="button" data-format="bold" data-target="' + targetField + '" title="Vet">' + '<strong>V</strong>' + '</button>' +
                '<button type="button" data-format="italic" data-target="' + targetField + '" title="Cursief">' + '<em>C</em>' + '</button>' +
                '<button type="button" data-format="link" data-target="' + targetField + '" title="Link">Link</button>' +
                (includeList ? '<button type="button" data-format="list" data-target="' + targetField + '" title="Opsomming">&bull;</button>' : '') +
            '</div>'
        );
    }

    function fieldsFor(block, isNested) {
        var out;
        switch (block.type) {
            case 'text':
                out = (
                    fieldRow('Eyebrow (optioneel, klein label boven de titel)', '<input type="text" data-field="eyebrow" value="' + escapeAttr(block.eyebrow) + '">') +
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    fieldRow('Tekst', richTextToolbar('body', true) + '<textarea data-field="body" rows="4">' + escapeHtml(block.body) + '</textarea>') +
                    '<p class="field-hint">Opmaak: **vet**, *cursief*, [linktekst](url), of een regel die begint met "- " voor een opsomming — of gebruik de knoppen hierboven.</p>'
                );
                break;
            case 'image':
                out = (
                    fieldRow('Afbeelding', (
                        '<div class="image-field">' +
                            '<input type="text" data-field="url" value="' + escapeAttr(block.url) + '" placeholder="https://... of upload hieronder">' +
                            '<label class="upload-button">' +
                                'Bestand kiezen…' +
                                '<input type="file" data-image-upload accept="image/jpeg,image/png,image/gif,image/webp">' +
                            '</label>' +
                        '</div>' +
                        '<p class="image-upload-status" data-role="upload-status"></p>' +
                        '<img class="image-preview" data-role="preview"' + (block.url ? ' src="' + escapeAttr(block.url) + '"' : ' hidden') + '>'
                    )) +
                    fieldRow('Alt-tekst (beschrijving voor toegankelijkheid)', '<input type="text" data-field="alt" value="' + escapeAttr(block.alt) + '" required>') +
                    fieldRow('Bijschrift (optioneel)', '<input type="text" data-field="caption" value="' + escapeAttr(block.caption) + '">')
                );
                break;
            case 'quote':
                out = (
                    fieldRow('Citaat', '<textarea data-field="text" rows="3">' + escapeHtml(block.text) + '</textarea>') +
                    fieldRow('Bron (optioneel)', '<input type="text" data-field="source" value="' + escapeAttr(block.source) + '">')
                );
                break;
            case 'list':
                out = (
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    fieldRow('Stijl', '<select data-field="style"><option value="bullet"' + (block.style === 'bullet' ? ' selected' : '') + '>Opsomming</option><option value="check"' + (block.style === 'check' ? ' selected' : '') + '>Vinkjes</option></select>') +
                    fieldRow('Items (één per regel)', richTextToolbar('itemsText') + '<textarea data-field="itemsText" rows="4">' + escapeHtml(block.itemsText) + '</textarea>') +
                    '<p class="field-hint">Opmaak: **vet**, *cursief*, [linktekst](url) — of gebruik de knoppen hierboven.</p>'
                );
                break;
            case 'buttons':
                out = (
                    fieldRow(
                        'Knoppen — één per regel, als "Tekst | link" (max 3)',
                        '<textarea data-field="buttonsText" rows="3" placeholder="Contact opnemen | /contact.php">' + escapeHtml(block.buttonsText) + '</textarea>'
                    ) +
                    fieldRow('Uitlijning', (
                        '<select data-field="align">' +
                            '<option value="left"' + (block.align !== 'center' && block.align !== 'right' ? ' selected' : '') + '>Links</option>' +
                            '<option value="center"' + (block.align === 'center' ? ' selected' : '') + '>Gecentreerd</option>' +
                            '<option value="right"' + (block.align === 'right' ? ' selected' : '') + '>Rechts</option>' +
                        '</select>'
                    ))
                );
                break;
            case 'calendar':
                out = (
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    '<p class="field-hint">Beschikbare tijdsloten beheer je apart via "Kalender" in het zijmenu.</p>'
                );
                break;
            case 'events':
                out = (
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    '<p class="field-hint">De evenementen zelf beheer je apart via "Evenementen" in het zijmenu — dit blok toont automatisch de eerstkomende, gepubliceerde evenementen.</p>'
                );
                break;
            case 'map':
                out = (
                    fieldRow('Adres van de praktijk', '<input type="text" data-field="address" value="' + escapeAttr(block.address) + '" placeholder="Straat 1, 2000 Antwerpen">') +
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    fieldRow('Weergave', (
                        '<select data-field="layout">' +
                            '<option value="box"' + (block.layout !== 'stretch' ? ' selected' : '') + '>Binnen de tekstkolom (box)</option>' +
                            '<option value="stretch"' + (block.layout === 'stretch' ? ' selected' : '') + '>Volledige breedte (stretch)</option>' +
                        '</select>'
                    )) +
                    '<p class="field-hint">Gratis Google Maps-kaart op basis van het adres — geen API-key nodig.</p>'
                );
                break;
            case 'media_text':
                out = (
                    fieldRow('Afbeelding', (
                        '<div class="image-field">' +
                            '<input type="text" data-field="url" value="' + escapeAttr(block.url) + '" placeholder="https://... of upload hieronder">' +
                            '<label class="upload-button">' +
                                'Bestand kiezen…' +
                                '<input type="file" data-image-upload accept="image/jpeg,image/png,image/gif,image/webp">' +
                            '</label>' +
                        '</div>' +
                        '<p class="image-upload-status" data-role="upload-status"></p>' +
                        '<img class="image-preview" data-role="preview"' + (block.url ? ' src="' + escapeAttr(block.url) + '"' : ' hidden') + '>'
                    )) +
                    fieldRow('Alt-tekst (beschrijving voor toegankelijkheid)', '<input type="text" data-field="alt" value="' + escapeAttr(block.alt) + '" required>') +
                    fieldRow('Bijschrift (optioneel)', '<input type="text" data-field="caption" value="' + escapeAttr(block.caption) + '">') +
                    fieldRow('Positie van de foto', (
                        '<select data-field="image_position">' +
                            '<option value="left"' + (block.image_position !== 'right' ? ' selected' : '') + '>Links (tekst rechts)</option>' +
                            '<option value="right"' + (block.image_position === 'right' ? ' selected' : '') + '>Rechts (tekst links)</option>' +
                        '</select>'
                    )) +
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    fieldRow('Tekst', richTextToolbar('body', true) + '<textarea data-field="body" rows="4">' + escapeHtml(block.body) + '</textarea>') +
                    '<p class="field-hint">Opmaak: **vet**, *cursief*, [linktekst](url), of een regel die begint met "- " voor een opsomming — of gebruik de knoppen hierboven.</p>'
                );
                break;
            default:
                return '';
        }
        return isNested ? out : out + backgroundFieldRow(block.background);
    }

    function columnsFieldsFor(block, path) {
        var countField = fieldRow('Aantal kolommen', (
            '<select data-field="column_count">' +
                '<option value="2"' + (block.column_count !== 3 ? ' selected' : '') + '>2 kolommen</option>' +
                '<option value="3"' + (block.column_count === 3 ? ' selected' : '') + '>3 kolommen</option>' +
            '</select>'
        ));

        var columnsHtml = (block.columns || []).map(function (children, c) {
            var childCards = children.map(function (child, j) {
                return renderBlockCard(child, path + ':' + c + ':' + j);
            }).join('');
            var toolbar = COLUMN_BLOCK_TYPES.map(function (t) {
                return '<button type="button" data-add="' + t.type + '" data-column-path="' + path + ':' + c + '">+ ' + t.label + '</button>';
            }).join('');
            var colBg = (block.column_backgrounds || [])[c] || 'none';
            var bgSelect = (
                '<select class="column-bg-select" data-column-bg="' + c + '">' +
                    '<option value="none"' + (colBg !== 'accent' && colBg !== 'surface' ? ' selected' : '') + '>Geen achtergrond</option>' +
                    '<option value="accent"' + (colBg === 'accent' ? ' selected' : '') + '>Accentkleur</option>' +
                    '<option value="surface"' + (colBg === 'surface' ? ' selected' : '') + '>Zachte kaart</option>' +
                '</select>'
            );

            return (
                '<div class="column-editor">' +
                    '<div class="column-editor-header">' +
                        '<p class="column-editor-label">Kolom ' + (c + 1) + '</p>' +
                        bgSelect +
                    '</div>' +
                    '<div class="block-editor block-editor-nested">' +
                        (childCards || '<p class="block-editor-empty">Nog geen blokken in deze kolom.</p>') +
                    '</div>' +
                    '<div class="block-toolbar block-toolbar-nested">' + toolbar + '</div>' +
                '</div>'
            );
        }).join('');

        return countField + '<div class="columns-editor">' + columnsHtml + '</div>' + backgroundFieldRow(block.background);
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function renderBlockCard(block, path) {
        var resolved = resolvePath(path);
        var isFirst = resolved.index === 0;
        var isLast = resolved.index === resolved.array.length - 1;

        return (
            '<div class="block-card" data-path="' + path + '" data-list="' + resolved.listId + '">' +
                '<div class="block-card-header">' +
                    '<span class="block-drag-handle" draggable="true" title="Verslepen om te herordenen" aria-hidden="true">⠿</span>' +
                    '<strong>' + (LABELS[block.type] || block.type) + '</strong>' +
                    '<div class="block-card-controls">' +
                        '<button type="button" data-action="up"' + (isFirst ? ' disabled' : '') + ' title="Naar boven">↑</button>' +
                        '<button type="button" data-action="down"' + (isLast ? ' disabled' : '') + ' title="Naar beneden">↓</button>' +
                        '<button type="button" data-action="remove" class="link-button-danger" title="Verwijderen">' +
                            '<svg class="admin-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                                '<path d="M4 7h16"></path><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"></path>' +
                                '<path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"></path><path d="M10 11v6M14 11v6"></path>' +
                            '</svg>' +
                            '<span class="visually-hidden">Verwijderen</span>' +
                        '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="block-card-fields">' + (block.type === 'columns' ? columnsFieldsFor(block, path) : fieldsFor(block, path.indexOf(':') !== -1)) + '</div>' +
            '</div>'
        );
    }

    function renderAll() {
        if (blocks.length === 0) {
            container.innerHTML = '<p class="block-editor-empty">Nog geen blokken. Voeg hieronder een blok toe.</p>';
            return;
        }

        container.innerHTML = blocks.map(function (block, index) {
            return renderBlockCard(block, String(index));
        }).join('');
    }

    container.addEventListener('input', function (e) {
        var field = e.target.getAttribute('data-field');
        if (!field) return;
        var card = e.target.closest('.block-card');
        var path = resolvePath(card.getAttribute('data-path'));
        path.array[path.index][field] = e.target.value;

        if (field === 'url') {
            setPreview(card, e.target.value);
        }
    });

    function setPreview(card, url) {
        var preview = card.querySelector('[data-role="preview"]');
        if (!preview) return;
        if (url) {
            preview.src = url;
            preview.hidden = false;
        } else {
            preview.hidden = true;
            preview.removeAttribute('src');
        }
    }

    function setUploadStatus(card, message, isError) {
        var status = card.querySelector('[data-role="upload-status"]');
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', Boolean(isError));
    }

    container.addEventListener('change', function (e) {
        if (!e.target.hasAttribute('data-image-upload')) return;

        var input = e.target;
        var card = input.closest('.block-card');
        var path = resolvePath(card.getAttribute('data-path'));
        var file = input.files && input.files[0];
        if (!file) return;

        var maxBytes = 5 * 1024 * 1024;
        if (file.size > maxBytes) {
            setUploadStatus(card, 'Bestand is te groot (max. 5 MB).', true);
            input.value = '';
            return;
        }

        setUploadStatus(card, 'Bezig met uploaden…', false);

        var csrfToken = form.querySelector('input[name="csrf_token"]').value;
        var formData = new FormData();
        formData.append('image', file);
        formData.append('csrf_token', csrfToken);

        fetch('upload-image.php', { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    setUploadStatus(card, (data && data.error) || 'Upload mislukt.', true);
                    return;
                }
                path.array[path.index].url = data.url;
                var urlInput = card.querySelector('[data-field="url"]');
                if (urlInput) urlInput.value = data.url;
                setPreview(card, data.url);
                setUploadStatus(card, 'Geüpload.', false);
            })
            .catch(function () {
                setUploadStatus(card, 'Upload mislukt door een netwerk- of serverfout.', true);
            })
            .finally(function () {
                input.value = '';
            });
    });

    container.addEventListener('change', function (e) {
        if (e.target.hasAttribute('data-column-bg')) {
            var bgCard = e.target.closest('.block-card');
            var bgPath = resolvePath(bgCard.getAttribute('data-path'));
            var bgBlock = bgPath.array[bgPath.index];
            var colIndex = parseInt(e.target.getAttribute('data-column-bg'), 10);
            var backgrounds = bgBlock.column_backgrounds || [];
            while (backgrounds.length <= colIndex) backgrounds.push('none');
            backgrounds[colIndex] = e.target.value;
            bgBlock.column_backgrounds = backgrounds;
            return;
        }

        var field = e.target.getAttribute('data-field');
        if (!field || e.target.tagName !== 'SELECT') return;
        var card = e.target.closest('.block-card');
        var path = resolvePath(card.getAttribute('data-path'));
        var block = path.array[path.index];

        if (field === 'column_count') {
            var newCount = parseInt(e.target.value, 10);
            var cols = block.columns || [];
            var droppedHasContent = cols.slice(newCount).some(function (children) { return children.length > 0; });
            if (newCount < cols.length && droppedHasContent) {
                if (!confirm('De extra kolom bevat al blokken. Toch naar ' + newCount + ' kolommen schakelen? De inhoud daarvan gaat dan verloren.')) {
                    e.target.value = String(block.column_count);
                    return;
                }
            }
            while (cols.length < newCount) cols.push([]);
            cols = cols.slice(0, newCount);
            block.columns = cols;
            block.column_count = newCount;

            var bgs = block.column_backgrounds || [];
            while (bgs.length < newCount) bgs.push('none');
            block.column_backgrounds = bgs.slice(0, newCount);

            renderAll();
            return;
        }

        block[field] = e.target.value;
    });

    // Wraps the textarea's current selection in before/after markers (e.g.
    // ** / **), or inserts placeholder text if nothing is selected, then
    // re-selects the wrapped text so repeated clicks/typing feel natural.
    function wrapSelection(textarea, before, after) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end) || 'tekst';
        textarea.value = value.slice(0, start) + before + selected + after + value.slice(end);
        textarea.focus();
        textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function insertLink(textarea) {
        var url = window.prompt('Link naar (bv. /contact, https://..., mailto:...)');
        if (!url) return;
        wrapSelection(textarea, '[', '](' + url.trim() + ')');
    }

    // Prefixes every non-blank line touching the current selection (or just
    // the current line, if nothing is selected) with "- ", the same bullet
    // syntax render_text_paragraphs() in functions.php turns into a list —
    // same principle as the bold/italic buttons, just line-based instead of
    // wrapping a span of text.
    function insertBulletLines(textarea) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;

        var lineStart = value.lastIndexOf('\n', start - 1) + 1;
        var lineEnd = value.indexOf('\n', end);
        if (lineEnd === -1) lineEnd = value.length;

        var lines = value.slice(lineStart, lineEnd).split('\n');
        var newLines = lines.map(function (line) {
            if (line.indexOf('- ') === 0) return line;
            if (line.trim() === '') return lines.length === 1 ? '- ' : line;
            return '- ' + line;
        });
        var newSegment = newLines.join('\n');

        textarea.value = value.slice(0, lineStart) + newSegment + value.slice(lineEnd);
        textarea.focus();
        // Collapsed cursor at the end, not a selection: unlike
        // wrapSelection()'s placeholder text (meant to be overtyped),
        // selecting the "- " prefix here would mean typing immediately
        // after replaces the bullet marker itself.
        var caret = lineStart + newSegment.length;
        textarea.setSelectionRange(caret, caret);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    container.addEventListener('click', function (e) {
        var formatBtn = e.target.closest('[data-format]');
        if (formatBtn) {
            var toolbarCard = formatBtn.closest('.block-card');
            var targetField = formatBtn.getAttribute('data-target');
            var textarea = toolbarCard.querySelector('textarea[data-field="' + targetField + '"]');
            if (!textarea) return;
            var format = formatBtn.getAttribute('data-format');
            if (format === 'bold') wrapSelection(textarea, '**', '**');
            else if (format === 'italic') wrapSelection(textarea, '*', '*');
            else if (format === 'link') insertLink(textarea);
            else if (format === 'list') insertBulletLines(textarea);
            return;
        }

        var actionBtn = e.target.closest('[data-action]');
        var action = actionBtn ? actionBtn.getAttribute('data-action') : null;
        if (action) {
            var card = actionBtn.closest('.block-card');
            var path = resolvePath(card.getAttribute('data-path'));

            if (action === 'remove') {
                path.array.splice(path.index, 1);
            } else if (action === 'up' && path.index > 0) {
                swapInArray(path.array, path.index, path.index - 1);
            } else if (action === 'down' && path.index < path.array.length - 1) {
                swapInArray(path.array, path.index, path.index + 1);
            }
            renderAll();
            return;
        }

        var addType = e.target.getAttribute('data-add');
        var columnPath = e.target.getAttribute('data-column-path');
        if (addType && columnPath) {
            var parts = columnPath.split(':').map(function (n) { return parseInt(n, 10); });
            var columnArray = blocks[parts[0]].columns[parts[1]];
            var block = newBlock(addType);
            if (block && columnArray.length < 10) {
                columnArray.push(block);
                renderAll();
            }
        }
    });

    // Drag & drop reordering: only the small handle icon is draggable (so
    // dragging/selecting text inside a field never starts a card drag), but
    // the whole card is the drop target. Only same-list drags are allowed —
    // reordering within the top-level list, or within one column — moving a
    // block between lists isn't supported yet (remove + re-add instead).
    var dragState = null;

    container.addEventListener('dragstart', function (e) {
        var card = e.target.closest('.block-card');
        if (!card) return;
        dragState = { path: card.getAttribute('data-path'), listId: card.getAttribute('data-list') };
        e.dataTransfer.effectAllowed = 'move';
        card.classList.add('is-dragging');
    });

    container.addEventListener('dragover', function (e) {
        if (!dragState) return;
        var card = e.target.closest('.block-card');
        if (!card || card.getAttribute('data-list') !== dragState.listId) return;
        e.preventDefault();
        card.classList.add('is-drag-over');
    });

    container.addEventListener('dragleave', function (e) {
        var card = e.target.closest('.block-card');
        if (card) card.classList.remove('is-drag-over');
    });

    container.addEventListener('drop', function (e) {
        if (!dragState) return;
        var card = e.target.closest('.block-card');
        if (!card || card.getAttribute('data-list') !== dragState.listId) return;
        e.preventDefault();

        var from = resolvePath(dragState.path);
        var to = resolvePath(card.getAttribute('data-path'));
        if (from.array === to.array && from.index !== to.index) {
            var moved = from.array.splice(from.index, 1)[0];
            from.array.splice(to.index, 0, moved);
            renderAll();
        }
    });

    container.addEventListener('dragend', function () {
        dragState = null;
        var stray = container.querySelectorAll('.is-dragging, .is-drag-over');
        for (var i = 0; i < stray.length; i++) {
            stray[i].classList.remove('is-dragging', 'is-drag-over');
        }
    });

    var toolbar = document.getElementById('block-toolbar');
    if (toolbar) {
        toolbar.innerHTML = TOOLBAR_BLOCK_TYPES.map(function (t) {
            return '<button type="button" data-add="' + t.type + '">+ ' + t.label + '</button>';
        }).join('');

        toolbar.addEventListener('click', function (e) {
            var type = e.target.getAttribute('data-add');
            if (!type) return;
            var block = newBlock(type);
            if (block) {
                blocks.push(block);
                renderAll();
                container.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    form.addEventListener('submit', function () {
        hiddenInput.value = JSON.stringify(toCanonicalShape(blocks));
    });

    renderAll();
})();

// Drag & drop page reordering in pages.php — same handle-based pattern as
// the block editor above, but reorders table rows and persists the new
// nav_order immediately via page-reorder.php (no form submit needed).
(function initPageReorder() {
    var tbody = document.getElementById('pages-tbody');
    var csrfInput = document.getElementById('pages-csrf');
    if (!tbody || !csrfInput) return;

    var status = document.getElementById('reorder-status');
    var dragRow = null;

    function setStatus(message, isError) {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', Boolean(isError));
    }

    function saveOrder() {
        var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (row) {
            return row.getAttribute('data-id');
        });
        setStatus('Bezig met opslaan…', false);

        var formData = new FormData();
        formData.append('order', JSON.stringify(ids));
        formData.append('csrf_token', csrfInput.value);

        fetch('page-reorder.php', { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    setStatus((data && data.error) || 'Opslaan van de volgorde is mislukt.', true);
                    return;
                }
                setStatus('Volgorde opgeslagen.', false);
            })
            .catch(function () {
                setStatus('Opslaan van de volgorde is mislukt door een netwerk- of serverfout.', true);
            });
    }

    tbody.addEventListener('dragstart', function (e) {
        var row = e.target.closest('tr[data-id]');
        if (!row) return;
        dragRow = row;
        e.dataTransfer.effectAllowed = 'move';
        row.classList.add('is-dragging');
    });

    tbody.addEventListener('dragover', function (e) {
        var row = e.target.closest('tr[data-id]');
        if (!row || !dragRow || row === dragRow) return;
        e.preventDefault();
        row.classList.add('is-drag-over');
    });

    tbody.addEventListener('dragleave', function (e) {
        var row = e.target.closest('tr[data-id]');
        if (row) row.classList.remove('is-drag-over');
    });

    tbody.addEventListener('drop', function (e) {
        var row = e.target.closest('tr[data-id]');
        if (!row || !dragRow || row === dragRow) return;
        e.preventDefault();
        row.classList.remove('is-drag-over');

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));
        var fromIndex = rows.indexOf(dragRow);
        var toIndex = rows.indexOf(row);
        if (fromIndex < toIndex) {
            row.parentNode.insertBefore(dragRow, row.nextSibling);
        } else {
            row.parentNode.insertBefore(dragRow, row);
        }
        saveOrder();
    });

    tbody.addEventListener('dragend', function () {
        if (dragRow) dragRow.classList.remove('is-dragging');
        dragRow = null;
        var stray = tbody.querySelectorAll('.is-drag-over');
        for (var i = 0; i < stray.length; i++) {
            stray[i].classList.remove('is-drag-over');
        }
    });
})();

// Newsletter send progress (admin/newsletter-edit.php, once status is
// "sending"): repeatedly calls newsletter-send.php, each call processing
// one small batch server-side — see BATCH_SIZE there for why this has to
// be batched rather than one request sending everyone. Reloads the page
// once the server reports done, which then renders the final "sent" view.
(function initNewsletterSendProgress() {
    var panel = document.getElementById('newsletter-send-progress');
    if (!panel || panel.getAttribute('data-sending') !== '1') return;

    var newsletterId = panel.getAttribute('data-newsletter-id');
    var csrfToken = panel.getAttribute('data-csrf');
    var countEl = panel.querySelector('[data-sent-count]');
    var barEl = panel.querySelector('[data-progress-bar]');

    function sendBatch() {
        var formData = new FormData();
        formData.append('id', newsletterId);
        formData.append('csrf_token', csrfToken);

        fetch('newsletter-send.php', { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;
                if (countEl) countEl.textContent = data.sent;
                if (barEl) barEl.value = data.sent;
                if (data.done) {
                    window.location.reload();
                } else {
                    sendBatch();
                }
            });
    }

    sendBatch();
})();
