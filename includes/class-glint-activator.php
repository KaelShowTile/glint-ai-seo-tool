<?php

/**
 * Fired during plugin activation
 */
class Glint_AI_SEO_Activator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'glint_seo_settings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			setting_key varchar(255) NOT NULL,
			setting_value longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Insert default settings if they don't exist
        $default_settings = array(
            'api_key' => '',
            'post_types' => wp_json_encode(array('post', 'page')),
            'meta_rules' => wp_json_encode(array())
        );

        foreach ($default_settings as $key => $value) {
            $wpdb->query(
                $wpdb->prepare(
                "INSERT IGNORE INTO $table_name (setting_key, setting_value) VALUES (%s, %s)",
                $key,
                $value
            )
            );
        }
    }

}
