<?php

class Glint_AI_SEO_Frontend
{

    public function init()
    {
        // Do not execute frontend filters in the admin dashboard
        if (is_admin()) {
            return;
        }

        // Native WordPress Hooks
        // pre_get_document_title overrides the native <title> generation in WP 4.4+
        add_filter('pre_get_document_title', array($this, 'override_title'), 999);
        // Hook to output the meta description if no other SEO plugin is active
        add_action('wp_head', array($this, 'output_meta_description'), 1);

        // Yoast SEO Hooks
        add_filter('wpseo_title', array($this, 'override_title'), 999);
        add_filter('wpseo_metadesc', array($this, 'override_description'), 999);

        // Rank Math SEO Hooks
        add_filter('rank_math/frontend/title', array($this, 'override_title'), 999);
        add_filter('rank_math/frontend/description', array($this, 'override_description'), 999);

        // All In One SEO Hooks
        add_filter('aioseo_title', array($this, 'override_title'), 999);
        add_filter('aioseo_description', array($this, 'override_description'), 999);
    }

    private function get_current_seo_meta($key)
    {
        // Only apply to singular posts/pages/custom types
        if (!is_singular()) {
            return false;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return false;
        }

        $val = get_post_meta($post_id, $key, true);
        return !empty($val) ? $val : false;
    }

    public function override_title($title)
    {
        $glint_title = $this->get_current_seo_meta('_glint_seo_title');
        if ($glint_title) {
            return wp_strip_all_tags($glint_title);
        }
        return $title; // Fallback to original
    }

    public function override_description($description)
    {
        $glint_desc = $this->get_current_seo_meta('_glint_seo_description');
        if ($glint_desc) {
            return wp_strip_all_tags($glint_desc);
        }
        return $description; // Fallback to original
    }

    public function output_meta_description()
    {
        $glint_desc = $this->get_current_seo_meta('_glint_seo_description');
        if ($glint_desc) {
            // Check if any major SEO plugin is active. If so, they handle the <meta> tag generation
            // and we rely on the specific plugin filters (like wpseo_metadesc) to override their output 
            // instead of blindly doubling up the <meta> tags in wp_head.
            $has_seo_plugin = defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION');

            if (!$has_seo_plugin) {
                echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($glint_desc)) . '" />' . "\n";
            }
        }
    }
}
