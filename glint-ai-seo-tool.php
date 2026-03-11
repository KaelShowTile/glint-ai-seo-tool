<?php
/**
 * Plugin Name:       ST AI SEO Tool
 * Description:       AI-powered SEO title and description generator using Gemini API. Features custom settings table and dynamic backend UI.
 * Version:           1.0.0
 * Author:            Kael
 * Text Domain:       glint-ai-seo-tool
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 */
define('GLINT_AI_SEO_VERSION', '1.0.0');
define('GLINT_AI_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GLINT_AI_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-activator.php';
register_activation_hook(__FILE__, array('Glint_AI_SEO_Activator', 'activate'));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once GLINT_AI_SEO_PLUGIN_DIR . 'includes/class-glint-ai-seo-tool.php';

/**
 * Begins execution of the plugin.
 */
function run_glint_ai_seo_tool()
{
	$plugin = new Glint_AI_SEO_Tool();
	$plugin->run();
}
run_glint_ai_seo_tool();
