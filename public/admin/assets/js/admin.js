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
        { type: 'buttons', label: 'Knoppen' }
    ];
    var LABELS = BLOCK_TYPES.reduce(function (acc, t) { acc[t.type] = t.label; return acc; }, {});

    function newBlock(type) {
        switch (type) {
            case 'text': return { type: 'text', heading: '', body: '' };
            case 'image': return { type: 'image', url: '', alt: '', caption: '' };
            case 'quote': return { type: 'quote', text: '', source: '' };
            case 'list': return { type: 'list', heading: '', style: 'bullet', itemsText: '' };
            case 'buttons': return { type: 'buttons', buttonsText: '' };
            default: return null;
        }
    }

    // Canonical (stored/rendered) shape <-> editor shape. Only list/buttons
    // differ: they use one plain textarea in the editor instead of a
    // repeating field group, which keeps the UI and this script simple.
    function toEditorShape(blocks) {
        return blocks.map(function (b) {
            if (b.type === 'list') {
                return { type: 'list', heading: b.heading || '', style: b.style || 'bullet', itemsText: (b.items || []).join('\n') };
            }
            if (b.type === 'buttons') {
                var lines = (b.buttons || []).map(function (btn) { return (btn.label || '') + ' | ' + (btn.url || ''); });
                return { type: 'buttons', buttonsText: lines.join('\n') };
            }
            return Object.assign({}, b);
        });
    }

    function toCanonicalShape(blocks) {
        return blocks.map(function (b) {
            if (b.type === 'list') {
                var items = (b.itemsText || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 20);
                return { type: 'list', heading: b.heading || '', style: b.style || 'bullet', items: items };
            }
            if (b.type === 'buttons') {
                var buttons = (b.buttonsText || '').split('\n').map(function (line) {
                    var parts = line.split('|');
                    return { label: (parts[0] || '').trim(), url: (parts.slice(1).join('|') || '').trim() };
                }).filter(function (btn) { return btn.label && btn.url; }).slice(0, 3);
                return { type: 'buttons', buttons: buttons };
            }
            return Object.assign({}, b);
        }).filter(function (b) { return b !== null; });
    }

    var blocks = [];
    try {
        var raw = initialData ? JSON.parse(initialData.textContent || '[]') : [];
        blocks = toEditorShape(Array.isArray(raw) ? raw : []);
    } catch (e) {
        blocks = [];
    }

    function fieldRow(labelText, inputHtml) {
        return '<label>' + labelText + '</label>' + inputHtml;
    }

    function fieldsFor(block) {
        switch (block.type) {
            case 'text':
                return (
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    fieldRow('Tekst', '<textarea data-field="body" rows="4">' + escapeHtml(block.body) + '</textarea>')
                );
            case 'image':
                return (
                    fieldRow('Afbeeldings-URL', '<input type="text" data-field="url" value="' + escapeAttr(block.url) + '" placeholder="https://... of /uploads/foto.jpg">') +
                    fieldRow('Alt-tekst (beschrijving voor toegankelijkheid)', '<input type="text" data-field="alt" value="' + escapeAttr(block.alt) + '">') +
                    fieldRow('Bijschrift (optioneel)', '<input type="text" data-field="caption" value="' + escapeAttr(block.caption) + '">')
                );
            case 'quote':
                return (
                    fieldRow('Citaat', '<textarea data-field="text" rows="3">' + escapeHtml(block.text) + '</textarea>') +
                    fieldRow('Bron (optioneel)', '<input type="text" data-field="source" value="' + escapeAttr(block.source) + '">')
                );
            case 'list':
                return (
                    fieldRow('Titel (optioneel)', '<input type="text" data-field="heading" value="' + escapeAttr(block.heading) + '">') +
                    fieldRow('Stijl', '<select data-field="style"><option value="bullet"' + (block.style === 'bullet' ? ' selected' : '') + '>Opsomming</option><option value="check"' + (block.style === 'check' ? ' selected' : '') + '>Vinkjes</option></select>') +
                    fieldRow('Items (één per regel)', '<textarea data-field="itemsText" rows="4">' + escapeHtml(block.itemsText) + '</textarea>')
                );
            case 'buttons':
                return fieldRow(
                    'Knoppen — één per regel, als "Tekst | link" (max 3)',
                    '<textarea data-field="buttonsText" rows="3" placeholder="Contact opnemen | /contact.php">' + escapeHtml(block.buttonsText) + '</textarea>'
                );
            default:
                return '';
        }
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function renderAll() {
        if (blocks.length === 0) {
            container.innerHTML = '<p class="block-editor-empty">Nog geen blokken. Voeg hieronder een blok toe.</p>';
            return;
        }

        container.innerHTML = blocks.map(function (block, index) {
            return (
                '<div class="block-card" data-index="' + index + '">' +
                    '<div class="block-card-header">' +
                        '<strong>' + (LABELS[block.type] || block.type) + '</strong>' +
                        '<div class="block-card-controls">' +
                            '<button type="button" data-action="up"' + (index === 0 ? ' disabled' : '') + ' title="Naar boven">↑</button>' +
                            '<button type="button" data-action="down"' + (index === blocks.length - 1 ? ' disabled' : '') + ' title="Naar beneden">↓</button>' +
                            '<button type="button" data-action="remove" class="link-button-danger" title="Verwijderen">Verwijderen</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="block-card-fields">' + fieldsFor(block) + '</div>' +
                '</div>'
            );
        }).join('');
    }

    container.addEventListener('input', function (e) {
        var field = e.target.getAttribute('data-field');
        if (!field) return;
        var card = e.target.closest('.block-card');
        var index = parseInt(card.getAttribute('data-index'), 10);
        blocks[index][field] = e.target.value;
    });

    container.addEventListener('change', function (e) {
        var field = e.target.getAttribute('data-field');
        if (!field || e.target.tagName !== 'SELECT') return;
        var card = e.target.closest('.block-card');
        var index = parseInt(card.getAttribute('data-index'), 10);
        blocks[index][field] = e.target.value;
    });

    container.addEventListener('click', function (e) {
        var action = e.target.getAttribute('data-action');
        if (!action) return;
        var card = e.target.closest('.block-card');
        var index = parseInt(card.getAttribute('data-index'), 10);

        if (action === 'remove') {
            blocks.splice(index, 1);
        } else if (action === 'up' && index > 0) {
            var prev = blocks[index - 1];
            blocks[index - 1] = blocks[index];
            blocks[index] = prev;
        } else if (action === 'down' && index < blocks.length - 1) {
            var next = blocks[index + 1];
            blocks[index + 1] = blocks[index];
            blocks[index] = next;
        }
        renderAll();
    });

    var toolbar = document.getElementById('block-toolbar');
    if (toolbar) {
        toolbar.innerHTML = BLOCK_TYPES.map(function (t) {
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
