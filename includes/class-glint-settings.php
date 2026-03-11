<?php

class Glint_AI_SEO_Settings
{

    public static function get_setting($key, $default = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'glint_seo_settings';

        // Ensure table exists (safe fallback if accessed before activation hook runs completely)
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $default;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT setting_value FROM $table_name WHERE setting_key = %s", $key));

        if ($row) {
            $val = $row->setting_value;
            $decoded = json_decode($val, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
        }
        return $default;
    }

    public static function update_setting($key, $value)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'glint_seo_settings';

        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }

        $wpdb->query(
            $wpdb->prepare(
            "INSERT INTO $table_name (setting_key, setting_value) VALUES (%s, %s) ON DUPLICATE KEY UPDATE setting_value = %s",
            $key,
            $value,
            $value
        )
        );
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'ST AI SEO',
            'ST AI SEO',
            'manage_options',
            'glint-ai-seo',
            array($this, 'display_plugin_admin_page'),
            'dashicons-superhero',
            81
        );
    }

    public function handle_form_submission()
    {
        if (isset($_POST['glint_seo_save_settings']) && check_admin_referer('glint_seo_settings_verify', 'glint_seo_settings_nonce')) {

            // Save API Key
            if (isset($_POST['glint_api_key'])) {
                self::update_setting('api_key', sanitize_text_field($_POST['glint_api_key']));
            }

            // Save Content Prompts
            if (isset($_POST['glint_content_prompts']) && is_array($_POST['glint_content_prompts'])) {
                $prompts = $_POST['glint_content_prompts'];
                $sanitized_prompts = array();
                foreach ($prompts as $pt => $prompt) {
                    $sanitized_prompts[sanitize_key($pt)] = sanitize_textarea_field($prompt);
                }
                self::update_setting('content_prompts', $sanitized_prompts);
            }

            // Save Content Settings (Source & Slug)
            if (isset($_POST['glint_content_settings']) && is_array($_POST['glint_content_settings'])) {
                $content_settings = $_POST['glint_content_settings'];
                $sanitized_settings = array();
                foreach ($content_settings as $pt => $data) {
                    $sanitized_settings[sanitize_key($pt)] = array(
                        'source' => sanitize_text_field(isset($data['source']) ? $data['source'] : 'default'),
                        'slug'   => sanitize_text_field(isset($data['slug']) ? $data['slug'] : '')
                    );
                }
                self::update_setting('content_settings', $sanitized_settings);
            }

            // Save Post Types
            if (isset($_POST['glint_post_types']) && is_array($_POST['glint_post_types'])) {
                $types = array_map('sanitize_text_field', $_POST['glint_post_types']);
                self::update_setting('post_types', $types);
            }
            else {
                self::update_setting('post_types', array());
            }

            // Save Meta Rules (Repeater Data)
            if (isset($_POST['glint_meta_rules'])) {
                // We expect a JSON string populated by our Vanilla JS script before submit
                $rules = json_decode(stripslashes($_POST['glint_meta_rules']), true);
                if (is_array($rules)) {
                    // Sanitize rules deeply
                    $sanitized_rules = array();
                    foreach ($rules as $pt => $fields) {
                        if (is_array($fields)) {
                            foreach ($fields as $field => $field_rules) {
                                if (is_array($field_rules)) {
                                    if (!isset($sanitized_rules[$pt])) $sanitized_rules[$pt] = [];
                                    foreach ($field_rules as $rule) {
                                        $sanitized_rules[$pt][$field][] = array(
                                            'meta_name' => sanitize_text_field(isset($rule['meta_name']) ? $rule['meta_name'] : ''),
                                            'meta_source' => sanitize_text_field(isset($rule['meta_source']) ? $rule['meta_source'] : ''),
                                            'select_meta' => sanitize_text_field(isset($rule['select_meta']) ? $rule['select_meta'] : ''),
                                            'meta_slug' => sanitize_text_field(isset($rule['meta_slug']) ? $rule['meta_slug'] : ''),
                                        );
                                    }
                                }
                            }
                        }
                    }
                    self::update_setting('meta_rules', $sanitized_rules);
                }
                else {
                    self::update_setting('meta_rules', array());
                }
            }

            add_settings_error('glint_seo_messages', 'glint_seo_message', __('Settings Saved', 'glint-ai-seo-tool'), 'updated');
        }
    }

    public function display_plugin_admin_page()
    {
        $api_key = self::get_setting('api_key', '');
        $saved_post_types = self::get_setting('post_types', array('post', 'page'));
        $content_prompts = self::get_setting('content_prompts', array());
        $content_settings = self::get_setting('content_settings', array());

        $all_post_types = get_post_types(array('public' => true), 'objects');

?>
		<div class="wrap glint-seo-wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<p class="description">Configure Gemini API and mapping rules to automatically generate SEO fields.</p>
			
			<?php settings_errors('glint_seo_messages'); ?>

			<form method="post" action="" id="glint-seo-settings-form">
				<?php wp_nonce_field('glint_seo_settings_verify', 'glint_seo_settings_nonce'); ?>
				
				<div class="glint-seo-card">
					<h2>1. General Settings & API</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="glint_api_key">Gemini API Key</label></th>
							<td>
								<input type="password" name="glint_api_key" id="glint_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
								<p class="description">Get your API key from Google AI Studio.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label>Supported Post Types</label></th>
							<td>
								<div class="glint-checkbox-grid">
									<?php foreach ($all_post_types as $pt): ?>
										<label>
											<input type="checkbox" name="glint_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $saved_post_types)); ?> />
											<?php echo esc_html($pt->label); ?>
										</label>
									<?php
        endforeach; ?>
								</div>
								<p class="description">The "Generate SEO" button will appear on these post types.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="glint-seo-card">
					<h2>2. Post Type Specific Settings</h2>
					<p class="description">Define which meta fields the AI should read when generating SEO title and description. Configure rules specifically for each post type to ensure maximum relevance.</p>
					
					<div id="glint-post-type-tabs">
						<?php foreach ($saved_post_types as $pt): ?>
							<?php $pt_obj = get_post_type_object($pt); ?>
							<?php if ($pt_obj): ?>
							<div class="glint-pt-block" data-pt="<?php echo esc_attr($pt); ?>">
								<h3><?php echo esc_html($pt_obj->label); ?> Settings</h3>
	
								<div class="glint-field-section">
									<h4>SEO Title Rules</h4>
									<div class="glint-repeater-container" data-field="title"></div>
									<button type="button" class="button button-secondary glint-add-rule-btn" data-field="title">+ Add Title Rule</button>
								</div>
								
								<div class="glint-field-section">
									<h4>SEO Description Rules</h4>
									<div class="glint-repeater-container" data-field="description"></div>
									<button type="button" class="button button-secondary glint-add-rule-btn" data-field="description">+ Add Description Rule</button>
								</div>

								<div class="glint-field-section">
									<h4>Post Content Rules</h4>
									<div class="glint-repeater-container" data-field="content"></div>
									<button type="button" class="button button-secondary glint-add-rule-btn" data-field="content">+ Add Content Rule</button>
								</div>

                                <div class="glint-field-section">
                                    <h4>Content Source Configuration</h4>
                                    <?php
                                    $c_settings = isset($content_settings[$pt]) ? $content_settings[$pt] : array('source' => 'default', 'slug' => '');
                                    ?>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="glint_content_settings[<?php echo esc_attr($pt); ?>][source]" value="custom" <?php checked($c_settings['source'], 'custom'); ?> />
                                            Use a Custom Field instead of the default Post Editor?
                                        </label>
                                    </p>
                                    <p>
                                        <input type="text" name="glint_content_settings[<?php echo esc_attr($pt); ?>][slug]" value="<?php echo esc_attr($c_settings['slug']); ?>" placeholder="Custom Field Slug (e.g. product_description)" class="regular-text" />
                                        <br><span class="description">Enter the ACF Field Name or Meta Key here.</span>
                                    </p>
                                </div>
                                
                                <div class="glint-field-section glint-prompt-section">
									<h4>Post Content Prompt</h4>
									<?php
                                    $default_prompt = "Write a blog post about [post_title]. Use the following metadata for context:\n[metadata]\n\nMake it engaging and informative. The content should be structured with headings and paragraphs.";
                                    $current_prompt = isset($content_prompts[$pt]) && !empty($content_prompts[$pt]) ? $content_prompts[$pt] : $default_prompt;
                                    ?>
									<textarea name="glint_content_prompts[<?php echo esc_attr($pt); ?>]" id="glint_content_prompt_<?php echo esc_attr($pt); ?>" rows="6" class="large-text"><?php echo esc_textarea($current_prompt); ?></textarea>
									<p class="description">Define the prompt for generating the main post content. Use placeholders like <code>[post_title]</code> and <code>[metadata]</code>.</p>
								</div>
							</div>
							<?php
            endif; ?>
						<?php
        endforeach; ?>
					</div>
					
					<?php if (empty($saved_post_types)): ?>
						<p class="description">Please select and save at least one supported post type above to configure its meta rules.</p>
					<?php
        endif; ?>

					<!-- Hidden field to store JSON output of repeater for saving -->
					<input type="hidden" name="glint_meta_rules" id="glint_meta_rules_input" value="" />
				</div>

				<p class="submit">
					<input type="submit" name="glint_seo_save_settings" id="submit" class="button button-primary" value="Save Settings">
				</p>
			</form>
		</div>
		<?php
    }
}
