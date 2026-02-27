document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('glint-seo-settings-form');
    const hiddenInput = document.getElementById('glint_meta_rules_input');
    const addBtns = document.querySelectorAll('.glint-add-rule-btn');

    // Make sure glintSeoData is actually provided
    if (typeof glintSeoData === 'undefined') {
        return;
    }

    const { core_metas, acf_metas, woo_metas, saved_rules } = glintSeoData;

    // Build the Options HTML string based on source
    function getOptionsForSource(source, pt, selectedValue = '') {
        let metas = {};
        if (source === 'core') metas = core_metas;
        if (source === 'acf') metas = acf_metas[pt] || {};
        if (source === 'woo') metas = woo_metas;

        let optionsHtml = '';

        // If it's empty, might not have any metas of that type installed
        if (Object.keys(metas).length === 0) {
            optionsHtml = `<option value="">-- No Metas Found --</option>`;
        } else {
            for (const [key, label] of Object.entries(metas)) {
                // Determine label if it is array from ACF etc or just key
                const displayLabel = typeof label === 'string' ? label : key;
                const selected = key === selectedValue ? 'selected' : '';
                optionsHtml += `<option value="${key}" ${selected}>${displayLabel}</option>`;
            }
        }
        return optionsHtml;
    }

    // Template renderer for a specific container
    function renderRow(container, pt, data = {}) {
        const {
            meta_name = '',
            meta_source = 'core',
            select_meta = '',
            meta_slug = ''
        } = data;

        const row = document.createElement('div');
        row.className = 'glint-repeater-row';

        row.innerHTML = `
            <input type="text" class="glint-meta-name" placeholder="Meta Name (e.g. Price)" value="${escapeHtml(meta_name)}" required>
            
            <select class="glint-meta-source">
                <option value="core" ${meta_source === 'core' ? 'selected' : ''}>WordPress Core</option>
                <option value="acf" ${meta_source === 'acf' ? 'selected' : ''}>ACF</option>
                <option value="woo" ${meta_source === 'woo' ? 'selected' : ''}>WooCommerce</option>
                <option value="custom" ${meta_source === 'custom' ? 'selected' : ''}>Custom/Other</option>
            </select>
            
            <select class="glint-select-meta ${meta_source === 'custom' ? 'glint-hidden' : ''}">
                ${meta_source !== 'custom' ? getOptionsForSource(meta_source, pt, select_meta) : ''}
            </select>
            
            <input type="text" class="glint-meta-slug ${meta_source === 'custom' ? '' : 'glint-hidden'}" placeholder="Custom Meta Slug (e.g. _my_custom_field)" value="${escapeHtml(meta_slug)}">
            
            <button type="button" class="button glint-remove-rule" title="Remove Rule"><span class="dashicons dashicons-trash" style="margin-top:3px;"></span></button>
        `;

        // Add event listeners for this specific row
        const sourceSelect = row.querySelector('.glint-meta-source');
        const metaSelect = row.querySelector('.glint-select-meta');
        const metaSlugInput = row.querySelector('.glint-meta-slug');
        const removeBtn = row.querySelector('.glint-remove-rule');

        sourceSelect.addEventListener('change', function (e) {
            const val = e.target.value;
            if (val === 'custom') {
                metaSelect.classList.add('glint-hidden');
                metaSlugInput.classList.remove('glint-hidden');
            } else {
                metaSelect.classList.remove('glint-hidden');
                metaSlugInput.classList.add('glint-hidden');
                metaSelect.innerHTML = getOptionsForSource(val, pt);
            }
        });

        removeBtn.addEventListener('click', function () {
            row.remove();
        });

        container.appendChild(row);
    }

    // Helper to sanitize strings
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initialize all repeaters on the page based on the saved rules struct: { "post": { "title": [], "description": [] } }
    const blocks = document.querySelectorAll('.glint-pt-block');
    blocks.forEach(block => {
        const pt = block.getAttribute('data-pt');

        // Init Title Rules Container
        const titleContainer = block.querySelector('.glint-repeater-container[data-field="title"]');
        if (titleContainer) {
            let titleRules = [];
            if (saved_rules && saved_rules[pt] && saved_rules[pt]['title']) {
                titleRules = saved_rules[pt]['title'];
            }
            if (Array.isArray(titleRules) && titleRules.length > 0) {
                titleRules.forEach(rule => renderRow(titleContainer, pt, rule));
            } else {
                renderRow(titleContainer, pt);
            }
        }

        // Init Description Rules Container
        const descContainer = block.querySelector('.glint-repeater-container[data-field="description"]');
        if (descContainer) {
            let descRules = [];
            if (saved_rules && saved_rules[pt] && saved_rules[pt]['description']) {
                descRules = saved_rules[pt]['description'];
            }
            if (Array.isArray(descRules) && descRules.length > 0) {
                descRules.forEach(rule => renderRow(descContainer, pt, rule));
            } else {
                renderRow(descContainer, pt);
            }
        }
    });

    // "Add Rule" buttons
    addBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const field = this.getAttribute('data-field');
            const ptBlock = this.closest('.glint-pt-block');
            const pt = ptBlock.getAttribute('data-pt');
            const container = ptBlock.querySelector(`.glint-repeater-container[data-field="${field}"]`);
            if (container) {
                renderRow(container, pt);
            }
        });
    });

    // On Form Submit, gather the states of all the repeater rows into a nested JSON object
    if (form) {
        form.addEventListener('submit', function () {
            const rules = {};

            document.querySelectorAll('.glint-pt-block').forEach(block => {
                const pt = block.getAttribute('data-pt');
                rules[pt] = { title: [], description: [] };

                // Get titles
                const titleRows = block.querySelectorAll('.glint-repeater-container[data-field="title"] .glint-repeater-row');
                titleRows.forEach(row => {
                    const name = row.querySelector('.glint-meta-name').value;
                    const src = row.querySelector('.glint-meta-source').value;
                    const sel = row.querySelector('.glint-select-meta').value;
                    const slug = row.querySelector('.glint-meta-slug').value;

                    if (name.trim() !== '') {
                        rules[pt].title.push({
                            meta_name: name,
                            meta_source: src,
                            select_meta: src === 'custom' ? '' : sel,
                            meta_slug: src === 'custom' ? slug : ''
                        });
                    }
                });

                // Get descriptions
                const descRows = block.querySelectorAll('.glint-repeater-container[data-field="description"] .glint-repeater-row');
                descRows.forEach(row => {
                    const name = row.querySelector('.glint-meta-name').value;
                    const src = row.querySelector('.glint-meta-source').value;
                    const sel = row.querySelector('.glint-select-meta').value;
                    const slug = row.querySelector('.glint-meta-slug').value;

                    if (name.trim() !== '') {
                        rules[pt].description.push({
                            meta_name: name,
                            meta_source: src,
                            select_meta: src === 'custom' ? '' : sel,
                            meta_slug: src === 'custom' ? slug : ''
                        });
                    }
                });
            });

            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(rules);
            }
        });
    }
});
