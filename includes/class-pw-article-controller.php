<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Article_Controller {
    private PW_Media_Manager $media_manager;

    public function __construct() {
        $this->media_manager = new PW_Media_Manager();
    }

    public function save_article(): void {
        PW_Security::verify_nonce_or_die('pinnwand_article_nonce', 'pinnwand_save_article');

        if (!is_user_logged_in()) {
            $this->respond_error(__('Bitte anmelden.', 'pinnwand'), 401);
            return;
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $is_new = $post_id <= 0;
        $user_id = get_current_user_id();

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
        $category = isset($_POST['category']) ? (int) $_POST['category'] : 0;
        $offer_type = isset($_POST['offer_type']) ? sanitize_key(wp_unslash($_POST['offer_type'])) : '';
        $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : '';

        if ($category <= 0) {
            $category = $this->get_fallback_category_id();
        }

        if ($title === '' || $content === '' || $category <= 0 || $offer_type === '') {
            $this->respond_error(__('Pflichtfelder fehlen.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        $valid_offer_types = array('verkauf', 'verleih');
        if (!in_array($offer_type, $valid_offer_types, true)) {
            $this->respond_error(__('Ungueltige Angebotsart.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        if (!current_user_can('edit_pw_artikels')) {
            $this->respond_error(__('Keine Berechtigung zum Speichern von Artikeln.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        if ($post_id > 0 && !PW_Security::can_edit_article($post_id)) {
            $this->respond_error(__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        if ($is_new && !current_user_can('manage_options')) {
            if (!PW_Rate_Limiter::acquire_create_lock($user_id)) {
                $this->respond_error(__('Bitte kurz warten und erneut versuchen.', 'pinnwand'), 429, $redirect_url);
                return;
            }

            $rate = PW_Rate_Limiter::check_create_allowed($user_id);
            if (!$rate['allowed']) {
                PW_Rate_Limiter::release_create_lock($user_id);
                $this->respond_error((string) $rate['message'], 429, $redirect_url);
                return;
            }
        }

        $post_args = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'pw_artikel',
            'post_status' => 'publish',
            'post_author' => $user_id,
        );

        if ($post_id > 0) {
            $post_args['ID'] = $post_id;
            $saved_id = wp_update_post($post_args, true);
            $action = 'article_updated';
        } else {
            $saved_id = wp_insert_post($post_args, true);
            $action = 'article_created';
        }

        if ($is_new && !current_user_can('manage_options')) {
            PW_Rate_Limiter::release_create_lock($user_id);
        }

        if (is_wp_error($saved_id)) {
            PW_Logger::error('article_save_failed', array('code' => $saved_id->get_error_code()));
            $this->respond_error($saved_id->get_error_message(), 500, $redirect_url);
            return;
        }

        wp_set_object_terms($saved_id, array($category), 'pw_kategorie', false);

        $tags_input = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
        if ($tags_input !== '') {
            $tags = array_filter(array_map('trim', explode(',', $tags_input)));
            wp_set_object_terms($saved_id, $tags, 'pw_tag', false);
        }

        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'available';
        $valid_statuses = array('available', 'borrowed', 'unavailable');
        if (!in_array($status, $valid_statuses, true)) {
            $status = 'available';
        }
        update_post_meta($saved_id, 'pw_status', $status);
        update_post_meta($saved_id, 'pw_offer_type', $offer_type);

        $price = isset($_POST['price']) ? (float) str_replace(',', '.', (string) wp_unslash($_POST['price'])) : 0.0;
        if ($price < 0) {
            $price = 0.0;
        }
        update_post_meta($saved_id, 'pw_price', $price);

        $media_result = $this->media_manager->process_article_images((int) $saved_id);
        if (!$media_result['success']) {
            PW_Logger::error('article_image_failed', array('post_id' => (string) $saved_id));
            $this->respond_error((string) $media_result['message'], 400, $redirect_url);
            return;
        }

        if (wp_doing_ajax()) {
            wp_send_json_success(
                array(
                    'message' => __('Artikel gespeichert.', 'pinnwand'),
                    'post_id' => $saved_id,
                    'action' => $action,
                )
            );
            return;
        }

        if ($redirect_url !== '') {
            $target_base = $redirect_url;
            if ($is_new) {
                $target_base = add_query_arg(
                    'article_id',
                    (string) $saved_id,
                    remove_query_arg('article_id', $redirect_url)
                );
            }
            $target = add_query_arg('pw_action', $action, $target_base);
        } else {
            $target = add_query_arg('pw_action', $action, get_permalink($saved_id));
        }
        PW_Security::safe_redirect($target);
    }

    public function change_article_status(): void {
        PW_Security::verify_nonce_or_die('pinnwand_status_nonce', 'pinnwand_change_status');

        if (!is_user_logged_in()) {
            $this->respond_error(__('Bitte anmelden.', 'pinnwand'), 401);
            return;
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : '';

        if ($post_id <= 0 || $status === '') {
            $this->respond_error(__('Fehlende Pflichtfelder.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        if (!PW_Security::can_edit_article($post_id)) {
            $this->respond_error(__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        $valid_statuses = array('available', 'borrowed', 'unavailable');
        if (!in_array($status, $valid_statuses, true)) {
            $this->respond_error(__('Ungueltiger Status.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        update_post_meta($post_id, 'pw_status', $status);

        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => __('Status aktualisiert.', 'pinnwand')));
            return;
        }

        $target = $redirect_url !== '' ? add_query_arg('pw_action', 'status_changed', $redirect_url) : home_url('/');
        PW_Security::safe_redirect($target);
    }

    public function delete_article(): void {
        PW_Security::verify_nonce_or_die('pinnwand_delete_nonce', 'pinnwand_delete_article', 'request');

        if (!is_user_logged_in()) {
            $this->respond_error(__('Bitte anmelden.', 'pinnwand'), 401);
            return;
        }

        $post_id = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
        $redirect_raw = isset($_REQUEST['redirect_url']) ? (string) wp_unslash($_REQUEST['redirect_url']) : '';
        if (strpos($redirect_raw, '%') !== false) {
            $redirect_raw = rawurldecode($redirect_raw);
        }
        $redirect_url = $redirect_raw !== '' ? esc_url_raw($redirect_raw) : '';

        if ($post_id <= 0) {
            $this->respond_error(__('Keine gueltige Artikel-ID.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        if (!PW_Security::can_edit_article($post_id)) {
            $this->respond_error(__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        $attachments = get_posts(
            array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_parent' => $post_id,
                'fields' => 'ids',
            )
        );

        foreach ($attachments as $attachment_id) {
            wp_delete_attachment((int) $attachment_id, true);
        }

        $deleted = wp_delete_post($post_id, true);
        if (!$deleted) {
            PW_Logger::error('article_delete_failed', array('post_id' => (string) $post_id));
            $this->respond_error(__('Artikel konnte nicht geloescht werden.', 'pinnwand'), 500, $redirect_url);
            return;
        }

        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => __('Artikel geloescht.', 'pinnwand')));
            return;
        }

        $target = $redirect_url !== '' ? add_query_arg('pw_action', 'article_deleted', $redirect_url) : home_url('/');
        PW_Security::safe_redirect($target);
    }

    public function remove_image(): void {
        PW_Security::verify_nonce_or_die('pinnwand_remove_image_nonce', 'pinnwand_remove_image');

        if (!is_user_logged_in()) {
            $this->respond_error(__('Bitte anmelden.', 'pinnwand'), 401);
            return;
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : '';

        if ($post_id <= 0 || $image_id <= 0) {
            $this->respond_error(__('Fehlende Bild- oder Artikel-ID.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        if (!PW_Security::can_edit_article($post_id)) {
            $this->respond_error(__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        $gallery_ids = $this->media_manager->get_gallery_ids($post_id);
        if (!in_array($image_id, $gallery_ids, true)) {
            $this->respond_error(__('Bild gehoert nicht zu diesem Artikel.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        wp_delete_attachment($image_id, true);
        $gallery_ids = array_values(
            array_filter(
                $gallery_ids,
                static function ($id) use ($image_id) {
                    return (int) $id !== (int) $image_id;
                }
            )
        );

        update_post_meta($post_id, 'pinnwand_gallery_ids', $gallery_ids);

        $new_primary = !empty($gallery_ids) ? (int) $gallery_ids[0] : 0;
        if ($new_primary > 0) {
            set_post_thumbnail($post_id, $new_primary);
            update_post_meta($post_id, 'pinnwand_primary_image_id', $new_primary);
        } else {
            delete_post_thumbnail($post_id);
            delete_post_meta($post_id, 'pinnwand_primary_image_id');
        }

        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => __('Bild entfernt.', 'pinnwand')));
            return;
        }

        $target = $redirect_url !== '' ? add_query_arg('pw_action', 'image_removed', $redirect_url) : home_url('/');
        PW_Security::safe_redirect($target);
    }

    public function set_primary_image(): void {
        PW_Security::verify_nonce_or_die('pinnwand_set_primary_image_nonce', 'pinnwand_set_primary_image');

        if (!is_user_logged_in()) {
            $this->respond_error(__('Bitte anmelden.', 'pinnwand'), 401);
            return;
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
        $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : '';

        if ($post_id <= 0 || $image_id <= 0) {
            $this->respond_error(__('Fehlende Bild- oder Artikel-ID.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        if (!PW_Security::can_edit_article($post_id)) {
            $this->respond_error(__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        $gallery_ids = $this->media_manager->get_gallery_ids($post_id);
        if (!in_array($image_id, $gallery_ids, true)) {
            $this->respond_error(__('Bild gehoert nicht zu diesem Artikel.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        set_post_thumbnail($post_id, $image_id);
        update_post_meta($post_id, 'pinnwand_primary_image_id', $image_id);

        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => __('Hauptbild gesetzt.', 'pinnwand')));
            return;
        }

        $target = $redirect_url !== '' ? add_query_arg('pw_action', 'primary_image_set', $redirect_url) : home_url('/');
        PW_Security::safe_redirect($target);
    }

    public function upload_image(): void {
        PW_Security::verify_nonce_or_die('pinnwand_upload_image_nonce', 'pinnwand_upload_image');

        if (!is_user_logged_in()) {
            $this->respond_error(__('Bitte anmelden.', 'pinnwand'), 401);
            return;
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : '';

        if ($post_id <= 0) {
            $this->respond_error(__('Bitte zuerst den Artikel speichern, danach Bilder hochladen.', 'pinnwand'), 400, $redirect_url);
            return;
        }

        if (!PW_Security::can_edit_article($post_id)) {
            $this->respond_error(__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand'), 403, $redirect_url);
            return;
        }

        $result = $this->media_manager->upload_single_image($post_id, 'pinnwand_upload_image');
        if (!$result['success']) {
            $this->respond_error((string) $result['message'], 400, $redirect_url);
            return;
        }

        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => __('Bild hochgeladen.', 'pinnwand')));
            return;
        }

        $target = $redirect_url !== '' ? add_query_arg('pw_action', 'image_uploaded', $redirect_url) : home_url('/');
        PW_Security::safe_redirect($target);
    }

    private function respond_error(string $message, int $status = 400, string $redirect_url = ''): void {
        PW_Logger::error('request_failed', array('status' => (string) $status, 'message' => $message));

        if (wp_doing_ajax()) {
            wp_send_json_error(array('message' => $message), $status);
            return;
        }

        $target = $redirect_url !== '' ? add_query_arg('error_message', rawurlencode($message), $redirect_url) : add_query_arg('error_message', rawurlencode($message), home_url('/'));
        PW_Security::safe_redirect($target);
    }

    private function get_fallback_category_id(): int {
        $ids = get_terms(
            array(
                'taxonomy' => 'pw_kategorie',
                'hide_empty' => false,
                'fields' => 'ids',
                'number' => 1,
            )
        );
        if (!is_wp_error($ids) && !empty($ids)) {
            return (int) $ids[0];
        }

        $created = wp_insert_term(__('Allerlei', 'pinnwand'), 'pw_kategorie');
        if (is_wp_error($created)) {
            return 0;
        }

        return isset($created['term_id']) ? (int) $created['term_id'] : 0;
    }
}
