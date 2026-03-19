<?php

class Glint_AI_SEO_Metabox
{

    public function add_meta_boxes()
    {
        $allowed_post_types = Glint_AI_SEO_Settings::get_setting('post_types', array('post', 'page'));

        if (empty($allowed_post_types)) {
            return;
        }

        add_meta_box(
            'glint_ai_seo_meta_box',
            'ST AI SEO Settings',
            array($this, 'render_meta_box_content'),
            $allowed_post_types,
            'normal',
            'high'
        );
    }

    public function render_meta_box_content($post)
    {
        wp_nonce_field('glint_seo_save_data', 'glint_seo_meta_box_nonce');

        $value_title = get_post_meta($post->ID, '_glint_seo_title', true);
        $value_desc = get_post_meta($post->ID, '_glint_seo_description', true);

?>
		<div class="glint-metabox-wrap">
			<div class="glint-metabox-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<p style="margin: 0; color: #50575e;">Utilize Gemini AI to generate customized SEO Titles and Descriptions based on this post's content and configured metadata.</p>
			</div>

			<div id="glint-ai-seo-feedback" style="display:none; padding: 10px; margin-bottom: 15px; border-radius: 4px; background: #f0f0f1; border-left: 4px solid #00a0d2;"></div>
            <div id="glint-ai-content-feedback" style="display:none; padding: 10px; margin-bottom: 15px; border-radius: 4px; background: #f0f0f1; border-left: 4px solid #00a0d2;"></div>

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="glint_seo_title">SEO Title</label></th>
						<td>
							<input type="text" id="glint_seo_title" name="glint_seo_title" value="<?php echo esc_attr($value_title); ?>" style="width: 100%; max-width: 600px; padding: 6px 10px;" />
							<p class="description"><span id="glint_seo_title_char_count"><?php echo strlen($value_title); ?></span> chars (Aim for &le; 60 chars)</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glint_seo_description">SEO Description</label></th>
						<td>
							<textarea id="glint_seo_description" name="glint_seo_description" rows="3" style="width: 100%; max-width: 600px; padding: 6px 10px;"><?php echo esc_textarea($value_desc); ?></textarea>
							<p class="description"><span id="glint_seo_desc_char_count"><?php echo strlen($value_desc); ?></span> chars (Aim for &le; 160 chars)</p>
						</td>
					</tr>
				</tbody>
			</table>

            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="button" class="button button-secondary button-large" id="glint-generate-seo-btn">
                    <span class="dashicons dashicons-superhero" style="line-height: normal; margin-top:2px; margin-right: 4px;"></span>
                    Generate SEO Data
                </button>

                <button type="button" class="button button-primary button-large" id="glint-generate-content-btn">
                    <span class="dashicons dashicons-edit" style="line-height: normal; margin-top:2px; margin-right: 4px;"></span>
                    Generate Post Content
                </button>
            </div>
		</div>
		<?php
    }

    public function save_meta_boxes($post_id)
    {
        if (!isset($_POST['glint_seo_meta_box_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['glint_seo_meta_box_nonce'], 'glint_seo_save_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['glint_seo_title'])) {
            update_post_meta($post_id, '_glint_seo_title', sanitize_text_field($_POST['glint_seo_title']));
        }

        if (isset($_POST['glint_seo_description'])) {
            update_post_meta($post_id, '_glint_seo_description', sanitize_textarea_field($_POST['glint_seo_description']));
        }
    }

    public function ajax_generate_seo()
    {
        set_time_limit(300);
        check_ajax_referer('glint_generate_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid Post ID'));
        }

        // Fetch rules
        $rules = Glint_AI_SEO_Settings::get_setting('meta_rules', array());
        $post_type = get_post_type($post_id);

        $title_meta_data = array();
        $desc_meta_data = array();

        // Helper to extract values based on rule structure
        $extract_meta = function ($field_rules) use ($post_id) {
            $data = array();
            if (is_array($field_rules) && !empty($field_rules)) {
                foreach ($field_rules as $rule) {
                    $name = isset($rule['meta_name']) ? $rule['meta_name'] : 'Unknown Meta';
                    $source = isset($rule['meta_source']) ? $rule['meta_source'] : 'core';
                    $key = '';

                    if ($source === 'custom') {
                        $key = isset($rule['meta_slug']) ? $rule['meta_slug'] : '';
                    }
                    else {
                        $key = isset($rule['select_meta']) ? $rule['select_meta'] : '';
                    }

                    if ($key === '')
                        continue;

                    $val = '';
                    if ($source === 'acf' && function_exists('get_field')) {
                        $val = get_field($key, $post_id);
                    }
                    else if ($source === 'core' && in_array($key, array('post_title', 'post_excerpt', 'post_name', 'post_date', 'post_author', 'permalink'))) {
                        if ($key === 'permalink') {
                            $val = get_permalink($post_id);
                        }
                        else {
                            $p = get_post($post_id);
                            $val = isset($p->$key) ? $p->$key : '';
                        }
                    }
                    else if ($source === 'woo' && strpos($key, 'pa_') === 0) {
                        // WooCommerce product attribute taxonomy — get term labels
                        $terms = wp_get_post_terms($post_id, $key, array('fields' => 'names'));
                        if (!is_wp_error($terms) && !empty($terms)) {
                            $val = implode(', ', $terms);
                        }
                    }
                    else if (taxonomy_exists($source)) {
                        // Taxonomy source — get term names
                        $parent_id = intval($key);
                        if ($parent_id === 0) {
                            // Root: get all terms assigned to this post
                            $terms = wp_get_post_terms($post_id, $source, array('fields' => 'names'));
                            if (!is_wp_error($terms) && !empty($terms)) {
                                $val = implode(', ', $terms);
                            }
                        }
                        else {
                            // Specific parent: get only child terms of this parent
                            $all_terms = wp_get_post_terms($post_id, $source);
                            $child_names = array();
                            if (!is_wp_error($all_terms)) {
                                foreach ($all_terms as $term) {
                                    if ($term->parent == $parent_id) {
                                        $child_names[] = $term->name;
                                    }
                                }
                            }
                            if (!empty($child_names)) {
                                $val = implode(', ', $child_names);
                            }
                        }
                    }
                    else {
                        $val = get_post_meta($post_id, $key, true);
                    }

                    if (!empty($val)) {
                        $data[$name] = $val;
                    }
                }
            }
            return $data;
        };

        if ($post_type && isset($rules[$post_type])) {
            $title_rules = isset($rules[$post_type]['title']) ? $rules[$post_type]['title'] : array();
            $desc_rules = isset($rules[$post_type]['description']) ? $rules[$post_type]['description'] : array();

            $title_meta_data = $extract_meta($title_rules);
            $desc_meta_data = $extract_meta($desc_rules);
        }

        // Use the content fetched from the JS editor context (since the post might not be saved yet)
        // If empty, fallback to the database post content just in case
        if (empty(trim($content))) {
            $post = get_post($post_id);
            $content = $post ? $post->post_content : '';
        }

        $ai_result = Glint_AI_SEO_API::generate_seo($content, $title_meta_data, $desc_meta_data);

        if (is_wp_error($ai_result)) {
            wp_send_json_error(array('message' => $ai_result->get_error_message()));
        }

        wp_send_json_success($ai_result);
    }

    public function ajax_generate_content()
    {
        set_time_limit(600); //limitation of generating content
        check_ajax_referer('glint_generate_content_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post_title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid Post ID'));
        }

        // Fetch rules and prompt
        $rules = Glint_AI_SEO_Settings::get_setting('meta_rules', array());
        $post_type = get_post_type($post_id);
        $prompt_templates = Glint_AI_SEO_Settings::get_setting('content_prompts', array());

        $default_prompt = "Write a blog post about [post_title]. Use the following metadata for context:\n[metadata]\n\nIf a permalink is provided in the metadata, you can use it to get more data from the post frontend.\n\nMake it engaging and informative. The content should be structured with headings and paragraphs.";
        $prompt_template = isset($prompt_templates[$post_type]) && !empty($prompt_templates[$post_type]) ? $prompt_templates[$post_type] : $default_prompt;

        $content_meta_data = array();

        // Helper to extract values based on rule structure
        $extract_meta = function ($field_rules) use ($post_id) {
            $data = array();
            if (is_array($field_rules) && !empty($field_rules)) {
                foreach ($field_rules as $rule) {
                    $name = isset($rule['meta_name']) ? $rule['meta_name'] : 'Unknown Meta';
                    $source = isset($rule['meta_source']) ? $rule['meta_source'] : 'core';
                    $key = '';

                    if ($source === 'custom') {
                        $key = isset($rule['meta_slug']) ? $rule['meta_slug'] : '';
                    }
                    else {
                        $key = isset($rule['select_meta']) ? $rule['select_meta'] : '';
                    }

                    if ($key === '')
                        continue;

                    $val = '';
                    if ($source === 'acf' && function_exists('get_field')) {
                        $val = get_field($key, $post_id);
                    }
                    else if ($source === 'core' && in_array($key, array('post_title', 'post_excerpt', 'post_name', 'post_date', 'post_author', 'permalink'))) {
                        if ($key === 'permalink') {
                            $val = get_permalink($post_id);
                        }
                        else {
                            $p = get_post($post_id);
                            $val = isset($p->$key) ? $p->$key : '';
                        }
                    }
                    else if ($source === 'woo' && strpos($key, 'pa_') === 0) {
                        // WooCommerce product attribute taxonomy — get term labels
                        $terms = wp_get_post_terms($post_id, $key, array('fields' => 'names'));
                        if (!is_wp_error($terms) && !empty($terms)) {
                            $val = implode(', ', $terms);
                        }
                    }
                    else if (taxonomy_exists($source)) {
                        // Taxonomy source — get term names
                        $parent_id = intval($key);
                        if ($parent_id === 0) {
                            // Root: get all terms assigned to this post
                            $terms = wp_get_post_terms($post_id, $source, array('fields' => 'names'));
                            if (!is_wp_error($terms) && !empty($terms)) {
                                $val = implode(', ', $terms);
                            }
                        }
                        else {
                            // Specific parent: get only child terms of this parent
                            $all_terms = wp_get_post_terms($post_id, $source);
                            $child_names = array();
                            if (!is_wp_error($all_terms)) {
                                foreach ($all_terms as $term) {
                                    if ($term->parent == $parent_id) {
                                        $child_names[] = $term->name;
                                    }
                                }
                            }
                            if (!empty($child_names)) {
                                $val = implode(', ', $child_names);
                            }
                        }
                    }
                    else {
                        $val = get_post_meta($post_id, $key, true);
                    }

                    if (!empty($val)) {
                        $data[$name] = $val;
                    }
                }
            }
            return $data;
        };

        if ($post_type && isset($rules[$post_type])) {
            $content_rules = isset($rules[$post_type]['content']) ? $rules[$post_type]['content'] : array();
            $content_meta_data = $extract_meta($content_rules);
        }

        $ai_result = Glint_AI_SEO_API::generate_content($post_title, $content_meta_data, $prompt_template);

        if (is_wp_error($ai_result)) {
            wp_send_json_error(array('message' => $ai_result->get_error_message()));
        }

        // The API returns the content directly as a string
        wp_send_json_success(array('content' => $ai_result));
    }
}
