<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Profile_Controller {
    public function save_profile(): void {
        PW_Security::verify_nonce_or_die('pinnwand_profile_nonce', 'pinnwand_save_profile');

        if (!is_user_logged_in()) {
            $this->redirect_with_error(__('Bitte anmelden.', 'pinnwand'));
        }

        $user_id = get_current_user_id();

        $first_name = sanitize_text_field(wp_unslash((string) ($_POST['first_name'] ?? '')));
        $last_name = sanitize_text_field(wp_unslash((string) ($_POST['last_name'] ?? '')));
        $phone = sanitize_text_field(wp_unslash((string) ($_POST['phone'] ?? '')));
        $address = sanitize_text_field(wp_unslash((string) ($_POST['address'] ?? '')));
        $zip = sanitize_text_field(wp_unslash((string) ($_POST['zip'] ?? '')));
        $city = sanitize_text_field(wp_unslash((string) ($_POST['city'] ?? '')));

        $required = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'address' => $address,
            'zip' => $zip,
            'city' => $city,
        );

        foreach ($required as $value) {
            if ($value === '') {
                $this->redirect_with_error(__('Pflichtfelder fehlen.', 'pinnwand'));
            }
        }

        if (!preg_match('/^\d{4,5}$/', $required['zip'])) {
            $this->redirect_with_error(__('PLZ muss 4 bis 5 Ziffern enthalten.', 'pinnwand'));
        }

        $updated = wp_update_user(
            array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
            )
        );
        if (is_wp_error($updated)) {
            $this->redirect_with_error($updated->get_error_message());
        }

        update_user_meta($user_id, 'pw_phone', $phone);
        update_user_meta($user_id, 'pw_address', $address);
        update_user_meta($user_id, 'pw_zip', $zip);
        update_user_meta($user_id, 'pw_city', $city);

        $redirect = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : home_url('/');
        PW_Security::safe_redirect(add_query_arg('pw_action', 'profile_saved', $redirect));
    }

    public function export_profile_csv(): void {
        PW_Security::verify_nonce_or_die('pinnwand_export_nonce', 'pinnwand_export_profile');

        if (!is_user_logged_in()) {
            wp_die(esc_html__('Bitte anmelden.', 'pinnwand'));
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        if (!$user) {
            wp_die(esc_html__('Benutzer nicht gefunden.', 'pinnwand'));
        }

        $rows = array();
        $rows[] = array('type', 'field', 'value');
        $rows[] = array('profile', 'user_login', $user->user_login);
        $rows[] = array('profile', 'user_email', $user->user_email);
        $rows[] = array('profile', 'first_name', (string) get_user_meta($user_id, 'first_name', true));
        $rows[] = array('profile', 'last_name', (string) get_user_meta($user_id, 'last_name', true));
        $rows[] = array('profile', 'pw_phone', (string) get_user_meta($user_id, 'pw_phone', true));
        $rows[] = array('profile', 'pw_address', (string) get_user_meta($user_id, 'pw_address', true));
        $rows[] = array('profile', 'pw_zip', (string) get_user_meta($user_id, 'pw_zip', true));
        $rows[] = array('profile', 'pw_city', (string) get_user_meta($user_id, 'pw_city', true));

        $posts = get_posts(
            array(
                'post_type' => 'pw_artikel',
                'author' => $user_id,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            )
        );

        foreach ($posts as $post) {
            $categories = wp_get_post_terms($post->ID, 'pw_kategorie', array('fields' => 'names'));
            $tags = wp_get_post_terms($post->ID, 'pw_tag', array('fields' => 'names'));

            $rows[] = array('article', 'id', (string) $post->ID);
            $rows[] = array('article', 'title', $post->post_title);
            $rows[] = array('article', 'description', wp_strip_all_tags($post->post_content));
            $rows[] = array('article', 'status', (string) get_post_meta($post->ID, 'pw_status', true));
            $rows[] = array('article', 'categories', implode('|', is_array($categories) ? $categories : array()));
            $rows[] = array('article', 'tags', implode('|', is_array($tags) ? $tags : array()));
            $rows[] = array('article', 'created', (string) get_post_time('c', true, $post));
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pinnwand-export-' . $user_id . '.csv"');

        $output = fopen('php://output', 'w');
        if (!$output) {
            wp_die(esc_html__('Export konnte nicht erstellt werden.', 'pinnwand'));
        }

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public function delete_profile(): void {
        PW_Security::verify_nonce_or_die('pinnwand_delete_profile_nonce', 'pinnwand_delete_profile');

        if (!is_user_logged_in()) {
            wp_die(esc_html__('Bitte anmelden.', 'pinnwand'));
        }

        $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options')) {
            $this->redirect_with_error(__('Administratoren koennen ihr Profil hier nicht loeschen.', 'pinnwand'));
        }

        $posts = get_posts(
            array(
                'post_type' => 'pw_artikel',
                'author' => $user_id,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => -1,
                'fields' => 'ids',
            )
        );

        foreach ($posts as $post_id) {
            $attachments = get_posts(
                array(
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_parent' => (int) $post_id,
                    'fields' => 'ids',
                )
            );
            foreach ($attachments as $attachment_id) {
                wp_delete_attachment((int) $attachment_id, true);
            }

            wp_delete_post((int) $post_id, true);
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        wp_destroy_current_session();
        wp_clear_auth_cookie();

        PW_Security::safe_redirect(add_query_arg('pw_action', 'profile_deleted', home_url('/')));
    }

    private function redirect_with_error(string $message): void {
        PW_Logger::error('profile_error', array('message' => $message));
        $target = wp_get_referer();
        if (!$target) {
            $target = home_url('/');
        }

        PW_Security::safe_redirect(add_query_arg('error_message', rawurlencode($message), $target));
    }
}
