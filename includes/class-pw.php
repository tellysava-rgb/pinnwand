<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW {
    public function run(): void {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies(): void {
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-post-types.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-user-roles.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-security.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-settings.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-logger.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-rate-limiter.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-media-manager.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-article-controller.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-profile-controller.php';
        require_once PINNWAND_PLUGIN_DIR . 'includes/class-pw-public.php';
    }

    private function register_hooks(): void {
        $post_types = new PW_Post_Types();
        add_action('init', array($post_types, 'register_post_types'));
        add_action('init', array($post_types, 'register_taxonomies'));

        $roles = new PW_User_Roles();
        add_action('init', array($roles, 'register_roles'));

        $settings = new PW_Settings();
        add_action('admin_menu', array($settings, 'register_admin_menu'));
        add_action('admin_init', array($settings, 'register_settings'));

        $logger = new PW_Logger();
        add_action('admin_menu', array($logger, 'register_admin_menu'));

        $articles = new PW_Article_Controller();
        add_action('admin_post_pinnwand_save_article', array($articles, 'save_article'));
        add_action('wp_ajax_pinnwand_save_article', array($articles, 'save_article'));
        add_action('admin_post_pinnwand_delete_article', array($articles, 'delete_article'));
        add_action('wp_ajax_pinnwand_delete_article', array($articles, 'delete_article'));
        add_action('admin_post_pinnwand_upload_image', array($articles, 'upload_image'));
        add_action('wp_ajax_pinnwand_upload_image', array($articles, 'upload_image'));
        add_action('admin_post_pinnwand_remove_image', array($articles, 'remove_image'));
        add_action('wp_ajax_pinnwand_remove_image', array($articles, 'remove_image'));
        add_action('admin_post_pinnwand_set_primary_image', array($articles, 'set_primary_image'));
        add_action('wp_ajax_pinnwand_set_primary_image', array($articles, 'set_primary_image'));
        add_action('admin_post_pinnwand_change_status', array($articles, 'change_article_status'));
        add_action('wp_ajax_pinnwand_change_status', array($articles, 'change_article_status'));

        $profile = new PW_Profile_Controller();
        add_action('admin_post_pinnwand_save_profile', array($profile, 'save_profile'));
        add_action('admin_post_pinnwand_export_profile', array($profile, 'export_profile_csv'));
        add_action('admin_post_pinnwand_delete_profile', array($profile, 'delete_profile'));

        $public = new PW_Public();
        add_action('init', array($public, 'register_shortcodes'));
        add_action('template_redirect', array($public, 'protect_single_article'));
        add_action('wp_enqueue_scripts', array($public, 'enqueue_assets'), 99);
        add_action('admin_init', array($public, 'restrict_backend_for_board_users'));
        add_filter('show_admin_bar', array($public, 'hide_admin_bar_for_board_users'));
        add_filter('login_redirect', array($public, 'redirect_non_admin_after_login'), 10, 3);
        add_action('wp_ajax_pinnwand_tag_suggestions', array($public, 'ajax_tag_suggestions'));
        add_action('register_form', array($public, 'render_registration_code_field'));
        add_filter('registration_errors', array($public, 'validate_registration_code'), 10, 3);
        add_action('user_register', array($public, 'handle_successful_registration'));
        add_filter('the_content', array($public, 'append_single_details'));
        add_filter('post_thumbnail_html', array($public, 'filter_single_featured_image'), 10, 5);

        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('pinnwand', false, dirname(plugin_basename(PINNWAND_PLUGIN_FILE)) . '/languages');
    }
}
