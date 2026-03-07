<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Security {
    public static function verify_nonce_or_die(string $nonce_field, string $action): void {
        $nonce = isset($_REQUEST[$nonce_field]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_field])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, $action)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen.', 'pinnwand')), 403);
            }

            wp_die(__('Sicherheitspruefung fehlgeschlagen.', 'pinnwand'), __('Fehler', 'pinnwand'), array('response' => 403));
        }
    }

    public static function can_edit_article(int $post_id): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'pw_artikel') {
            return false;
        }

        return (int) $post->post_author === (int) get_current_user_id();
    }

    public static function safe_redirect(string $requested_url, string $fallback = ''): void {
        $fallback_url = $fallback !== '' ? $fallback : home_url('/');
        $target = wp_validate_redirect($requested_url, $fallback_url);
        wp_safe_redirect($target);
        exit;
    }
}
