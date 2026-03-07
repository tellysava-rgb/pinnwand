<?php
/**
 * Plugin Name: Pinnwand
 * Description: Eine digitale Pinnwand fuer das Verleihen und Finden von Artikeln.
 * Version: 0.1.0
 * Author: Pinnwand Team
 * Text Domain: pinnwand
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PINNWAND_VERSION', '0.1.0');
define('PINNWAND_PLUGIN_FILE', __FILE__);
define('PINNWAND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PINNWAND_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw.php';

function pinnwand_activate(): void {
    require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-post-types.php';
    require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-user-roles.php';
    require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-settings.php';

    $post_types = new PW_Post_Types();
    $post_types->register_post_types();
    $post_types->register_taxonomies();

    $roles = new PW_User_Roles();
    $roles->register_roles();

    add_option('pinnwand_version', PINNWAND_VERSION);
    add_option('pinnwand_db_version', PINNWAND_VERSION);
    add_option('pinnwand_settings', PW_Settings::get_defaults());
    add_option('pinnwand_error_log', array());

    flush_rewrite_rules();
}

function pinnwand_deactivate(): void {
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'pinnwand_activate');
register_deactivation_hook(__FILE__, 'pinnwand_deactivate');

function pinnwand_run(): void {
    $plugin = new PW();
    $plugin->run();
}

pinnwand_run();
