document.addEventListener('DOMContentLoaded', function () {

    const btn = document.getElementById('glint-generate-seo-btn');
    if (!btn) return;

    const titleInput = document.getElementById('glint_seo_title');
    const descInput = document.getElementById('glint_seo_description');
    const feedback = document.getElementById('glint-ai-seo-feedback');
    const titleCharCount = document.getElementById('glint_seo_title_char_count');
    const descCharCount = document.getElementById('glint_seo_desc_char_count');

    // Attach char counters listeners
    if (titleInput && titleCharCount) {
        titleInput.addEventListener('input', () => { titleCharCount.innerText = titleInput.value.length; });
    }
    if (descInput && descCharCount) {
        descInput.addEventListener('input', () => { descCharCount.innerText = descInput.value.length; });
    }

    btn.addEventListener('click', function () {
        if (typeof glintSeoMetabox === 'undefined') return;

        // Try to get dynamic content from editor
        let content = '';
        const postIdEle = document.getElementById('post_ID');
        if (!postIdEle) return; // Not on standard edit screen
        const postId = postIdEle.value;

        // Check for Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            content = wp.data.select('core/editor').getEditedPostContent();
        }
        // Fallback or Classic TinyMCE
        else if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            content = tinyMCE.activeEditor.getContent();
        }
        // Textarea fallback
        else {
            const contentEle = document.getElementById('content');
            if (contentEle) {
                content = contentEle.value;
            }
        }

        feedback.style.display = 'block';
        feedback.style.borderLeftColor = '#f56e28'; // Processing orange
        feedback.innerText = 'Generating SEO data with Gemini AI. This may take a few seconds...';
        btn.disabled = true;

        // Save original text in case we want a spinner class instead
        const originalBtnHtml = btn.innerHTML;
        btn.innerHTML = 'Generating...';

        const data = new URLSearchParams({
            action: 'glint_generate_seo',
            nonce: glintSeoMetabox.nonce,
            post_id: postId,
            content: content
        });

        fetch(glintSeoMetabox.ajax_url, {
            method: 'POST',
            body: data,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            }
        })
            .then(response => response.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;

                if (res.success && res.data) {
                    if (res.data.title && titleInput) {
                        titleInput.value = res.data.title;
                        if (titleCharCount) titleCharCount.innerText = res.data.title.length;
                    }
                    if (res.data.description && descInput) {
                        descInput.value = res.data.description;
                        if (descCharCount) descCharCount.innerText = res.data.description.length;
                    }
                    feedback.style.borderLeftColor = '#46b450'; // Green success
                    feedback.innerText = '✅ SEO Title and Description generated successfully!';
                } else {
                    feedback.style.borderLeftColor = '#dc3232'; // Red error
                    feedback.innerText = '❌ Error: ' + (res.data.message || 'Unknown error occurred.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
                feedback.style.borderLeftColor = '#dc3232';
                feedback.innerText = '❌ Network Error: Could not connect to the server.';
                console.error(err);
            });

    });

});
