<?php

class Glint_AI_SEO_Tool
{

    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    private function load_dependencies()
    {
        require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-meta-sources.php';
        require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-settings.php';
        require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-metabox.php';
        require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-api.php';
        require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-frontend.php';
    }

    private function define_admin_hooks()
    {
        $settings = new Glint_AI_SEO_Settings();
        $metabox = new Glint_AI_SEO_Metabox();

        add_action('admin_menu', array($settings, 'add_plugin_admin_menu'));
        add_action('admin_init', array($settings, 'handle_form_submission'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        add_action('edit_form_after_title', array($this, 'add_generate_content_button'));
        add_action('add_meta_boxes', array($metabox, 'add_meta_boxes'));
        add_action('save_post', array($metabox, 'save_meta_boxes'));
        add_action('wp_ajax_glint_generate_seo', array($metabox, 'ajax_generate_seo'));
        add_action('wp_ajax_glint_generate_content', array($metabox, 'ajax_generate_content'));
    }

    public function enqueue_admin_scripts($hook)
    {
        // Enqueue on settings page OR post edit screens
        global $post_type;
        $allowed_post_types = Glint_AI_SEO_Settings::get_setting('post_types', array('post', 'page'));

        $is_settings_page = strpos($hook, 'glint-ai-seo') !== false;
        $is_post_edit = ($hook === 'post.php' || $hook === 'post-new.php') && in_array($post_type, $allowed_post_types);

        if ($is_settings_page || $is_post_edit) {
            wp_enqueue_style('glint-ai-seo-admin-css', GLINT_AI_SEO_PLUGIN_URL . 'assets/css/admin.css', array(), GLINT_AI_SEO_VERSION, 'all');
        }

        if ($is_settings_page) {
            wp_enqueue_script('glint-ai-seo-admin-js', GLINT_AI_SEO_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), GLINT_AI_SEO_VERSION, true);

            // Localize script with meta sources
            $meta_sources = new Glint_AI_SEO_Meta_Sources();
            wp_localize_script('glint-ai-seo-admin-js', 'glintSeoData', array(
                'core_metas' => $meta_sources->get_core_metas(),
                'acf_metas' => $meta_sources->get_acf_metas(),
                'woo_metas' => $meta_sources->get_woo_metas(),
                'saved_rules' => Glint_AI_SEO_Settings::get_setting('meta_rules', array())
            ));
        }

        if ($is_post_edit) {
            wp_enqueue_script('glint-ai-seo-metabox-js', GLINT_AI_SEO_PLUGIN_URL . 'assets/js/metabox.js', array('jquery'), GLINT_AI_SEO_VERSION, true);
            wp_localize_script('glint-ai-seo-metabox-js', 'glintSeoMetabox', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'seo_nonce' => wp_create_nonce('glint_generate_seo_nonce'),
                'content_nonce' => wp_create_nonce('glint_generate_content_nonce')
            ));
        }
    }

    public function run()
    {
        $frontend = new Glint_AI_SEO_Frontend();
        $frontend->init();
    }

    public function add_generate_content_button($post)
    {
        $allowed_post_types = Glint_AI_SEO_Settings::get_setting('post_types', array('post', 'page'));
        if (in_array($post->post_type, $allowed_post_types)) {
            echo '<div style="margin: 10px 0; position: relative; z-index: 10;">'; // z-index to be above editor toolbars
            echo '<button type="button" class="button button-primary button-large" id="glint-generate-content-btn">';
            echo '<span class="dashicons dashicons-superhero" style="line-height: normal; margin-top:2px; margin-right: 4px;"></span>';
            echo 'Generate Post Content';
            echo '</button>';
            echo '<div id="glint-ai-content-feedback" style="display:none; padding: 10px; margin-top: 10px; border-radius: 4px; background: #f0f0f1; border-left: 4px solid #00a0d2;"></div>';
            echo '</div>';
        }
    }

}
