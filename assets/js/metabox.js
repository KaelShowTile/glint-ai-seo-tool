document.addEventListener('DOMContentLoaded', function () {

    // Helper: Get Config for current post type
    function getContentConfig() {
        if (typeof glintSeoMetabox === 'undefined') return { source: 'default', slug: '' };
        const pt = glintSeoMetabox.post_type;
        if (glintSeoMetabox.content_settings && glintSeoMetabox.content_settings[pt]) {
            return glintSeoMetabox.content_settings[pt];
        }
        return { source: 'default', slug: '' };
    }

    // Helper: Get Content (Handles Default Editor, Gutenberg, ACF, Custom Fields)
    function getPostContent() {
        const config = getContentConfig();
        
        if (config.source === 'custom' && config.slug) {
            // Try ACF Wrapper Detection first (most robust for ACF)
            const acfWrapper = document.querySelector('.acf-field[data-name="' + config.slug + '"]');
            if (acfWrapper) {
                const textarea = acfWrapper.querySelector('textarea');
                if (textarea) {
                    // Check if it's a TinyMCE instance
                    if (typeof tinyMCE !== 'undefined' && textarea.id && tinyMCE.get(textarea.id)) {
                        return tinyMCE.get(textarea.id).getContent();
                    }
                    return textarea.value;
                }
            }
            
            // Fallback: Standard Input/Textarea by name
            const el = document.querySelector('[name="' + config.slug + '"]');
            if (el) return el.value;
        }

        // Default Handling
        // Check for Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            // Check if Gutenberg is actually active/initialized
            const editor = wp.data.select('core/editor');
            if (editor && typeof editor.getEditedPostContent === 'function') {
                return editor.getEditedPostContent();
            }
        }
        // Classic TinyMCE
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            return tinyMCE.activeEditor.getContent();
        }
        // Textarea fallback
        const contentEle = document.getElementById('content');
        if (contentEle) {
            return contentEle.value;
        }

        return '';
    }

    // --- Generate SEO Title & Description ---
    const seoBtn = document.getElementById('glint-generate-seo-btn');
    if (seoBtn) {

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

        seoBtn.addEventListener('click', function () {
            if (typeof glintSeoMetabox === 'undefined') return;

            // Try to get dynamic content from editor
            let content = getPostContent();
            const postIdEle = document.getElementById('post_ID');
            if (!postIdEle) return;
            const postId = postIdEle.value;
            
            feedback.style.display = 'block';
            feedback.style.borderLeftColor = '#f56e28'; // Processing orange
            feedback.innerText = 'Generating SEO data with Gemini AI. This may take a few seconds...';
            seoBtn.disabled = true;

            // Save original text in case we want a spinner class instead
            const originalBtnHtml = seoBtn.innerHTML;
            seoBtn.innerHTML = 'Generating...';

            const data = new URLSearchParams({
                action: 'glint_generate_seo',
                nonce: glintSeoMetabox.seo_nonce,
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
                    seoBtn.disabled = false;
                    seoBtn.innerHTML = originalBtnHtml;

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
                    seoBtn.disabled = false;
                    seoBtn.innerHTML = originalBtnHtml;
                    feedback.style.borderLeftColor = '#dc3232';
                    feedback.innerText = '❌ Network Error: Could not connect to the server.';
                    console.error(err);
                });

        });
    }

    // --- Generate Post Content ---
    const contentBtn = document.getElementById('glint-generate-content-btn');
    if (contentBtn) {
        const contentFeedback = document.getElementById('glint-ai-content-feedback');

        contentBtn.addEventListener('click', function () {
            if (typeof glintSeoMetabox === 'undefined') return;

            const postIdEle = document.getElementById('post_ID');
            if (!postIdEle) return;
            const postId = postIdEle.value;

            const postTitleEle = document.getElementById('title') || document.querySelector('.editor-post-title__input');
            const postTitle = postTitleEle ? postTitleEle.value : '';

            contentFeedback.style.display = 'block';
            contentFeedback.style.borderLeftColor = '#f56e28'; // Processing orange
            contentFeedback.innerText = 'Generating post content with Gemini AI. This may take a while...';
            contentBtn.disabled = true;

            const originalBtnHtml = contentBtn.innerHTML;
            contentBtn.innerHTML = 'Generating...';

            const data = new URLSearchParams({
                action: 'glint_generate_content',
                nonce: glintSeoMetabox.content_nonce,
                post_id: postId,
                post_title: postTitle
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
                    contentBtn.disabled = false;
                    contentBtn.innerHTML = originalBtnHtml;

                    if (res.success && res.data && res.data.content) {
                        const generatedContent = res.data.content;

                        const config = getContentConfig();
                        let inserted = false;

                        // 1. Try Custom Field Insertion
                        if (config.source === 'custom' && config.slug) {
                             const acfWrapper = document.querySelector('.acf-field[data-name="' + config.slug + '"]');
                             if (acfWrapper) {
                                 const textarea = acfWrapper.querySelector('textarea');
                                 if (textarea) {
                                     if (typeof tinyMCE !== 'undefined' && textarea.id && tinyMCE.get(textarea.id)) {
                                         tinyMCE.get(textarea.id).setContent(generatedContent);
                                         inserted = true;
                                     } else {
                                         textarea.value = generatedContent;
                                         inserted = true;
                                     }
                                 }
                             } else {
                                 // Fallback simple selector
                                 const el = document.querySelector('[name="' + config.slug + '"]');
                                 if (el) { el.value = generatedContent; inserted = true; }
                             }
                        }

                        // 2. Default Insertion (if not custom or custom failed)
                        if (!inserted) {
                             // Gutenberg
                             if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                                const blocks = wp.blocks.rawHandler({ HTML: generatedContent });
                                wp.data.dispatch('core/editor').resetEditorBlocks(blocks);
                            }
                            // Classic TinyMCE
                            else if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                                tinyMCE.get('content').setContent(generatedContent);
                            }
                            // Textarea
                            else {
                                const contentEle = document.getElementById('content');
                                if (contentEle) contentEle.value = generatedContent;
                            }
                        }

                        contentFeedback.style.borderLeftColor = '#46b450'; // Green success
                        contentFeedback.innerText = '✅ Post content generated and inserted successfully!';

                    } else {
                        contentFeedback.style.borderLeftColor = '#dc3232'; // Red error
                        contentFeedback.innerText = '❌ Error: ' + (res.data.message || 'Unknown error occurred.');
                    }
                })
                .catch(err => {
                    contentBtn.disabled = false;
                    contentBtn.innerHTML = originalBtnHtml;
                    contentFeedback.style.borderLeftColor = '#dc3232';
                    contentFeedback.innerText = '❌ Network Error: Could not connect to the server.';
                    console.error(err);
                });

        });
    }

});
