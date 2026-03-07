<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Media_Manager {
    public function upload_single_image(int $post_id, string $field_name = 'pinnwand_upload_image'): array {
        if (!isset($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return array(
                'success' => false,
                'message' => __('Kein Bild ausgewaehlt.', 'pinnwand'),
            );
        }

        $file = $_FILES[$field_name];
        $settings = PW_Settings::get();
        $max_images = (int) $settings['max_images'];
        $max_single_bytes = (int) $settings['max_image_mb'] * 1024 * 1024;

        $gallery_ids = $this->get_gallery_ids($post_id);
        if (count($gallery_ids) >= $max_images) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %d max images */
                    __('Maximal %d Bilder pro Artikel erlaubt.', 'pinnwand'),
                    $max_images
                ),
            );
        }

        $safe_name = sanitize_file_name((string) ($file['name'] ?? ''));
        $tmp_name = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $type = (string) ($file['type'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($safe_name === '' || $tmp_name === '') {
            return array(
                'success' => false,
                'message' => __('Kein Bild ausgewaehlt.', 'pinnwand'),
            );
        }

        if ($error !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => $this->build_upload_error_message($error),
            );
        }

        if ($size > $max_single_bytes) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %d max MB */
                    __('Ein Bild ueberschreitet das Limit von %d MB.', 'pinnwand'),
                    (int) $settings['max_image_mb']
                ),
            );
        }

        $allowed_mimes = array_map('strval', (array) ($settings['allowed_mimes'] ?? array()));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');
        $fileinfo = wp_check_filetype_and_ext($tmp_name, $safe_name);
        $ext = isset($fileinfo['ext']) ? strtolower((string) $fileinfo['ext']) : '';
        $real_type = isset($fileinfo['type']) ? (string) $fileinfo['type'] : '';
        $mime_allowed = in_array($type, $allowed_mimes, true) || in_array($real_type, $allowed_mimes, true);
        $ext_allowed = in_array($ext, $allowed_extensions, true);
        if (!$mime_allowed && !$ext_allowed) {
            return array(
                'success' => false,
                'message' => __('Dateityp nicht erlaubt. Erlaubt: jpg, jpeg, png, webp.', 'pinnwand'),
            );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload($field_name, $post_id);
        if (is_wp_error($attachment_id)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s error message */
                    __('Bild konnte nicht gespeichert werden: %s', 'pinnwand'),
                    $attachment_id->get_error_message()
                ),
            );
        }

        $attachment_id = (int) $attachment_id;
        $this->optimize_attachment_image($attachment_id, $settings);

        $gallery_ids[] = $attachment_id;
        $gallery_ids = array_values(array_unique(array_map('intval', $gallery_ids)));
        update_post_meta($post_id, 'pinnwand_gallery_ids', $gallery_ids);

        $primary_id = (int) get_post_meta($post_id, 'pinnwand_primary_image_id', true);
        if ($primary_id <= 0) {
            set_post_thumbnail($post_id, $attachment_id);
            update_post_meta($post_id, 'pinnwand_primary_image_id', $attachment_id);
        }

        return array('success' => true, 'message' => '');
    }

    public function process_article_images(int $post_id): array {
        $settings = PW_Settings::get();
        $max_images = (int) $settings['max_images'];
        $max_single_bytes = (int) $settings['max_image_mb'] * 1024 * 1024;
        $max_total_bytes = (int) $settings['max_total_image_mb'] * 1024 * 1024;

        $gallery_ids = $this->get_gallery_ids($post_id);
        $remove_ids = $this->get_remove_image_ids();
        $gallery_ids = $this->remove_images($gallery_ids, $remove_ids);

        $remaining_slots = max(0, $max_images - count($gallery_ids));
        $uploaded = $this->handle_new_uploads($post_id, $max_single_bytes, $max_total_bytes, $settings, $remaining_slots);
        if (!$uploaded['success']) {
            return $uploaded;
        }

        $gallery_ids = array_merge($gallery_ids, $uploaded['attachment_ids']);
        $gallery_ids = array_values(array_unique(array_map('intval', $gallery_ids)));

        if (count($gallery_ids) > $max_images) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %d max images */
                    __('Maximal %d Bilder pro Artikel erlaubt.', 'pinnwand'),
                    $max_images
                ),
            );
        }

        update_post_meta($post_id, 'pinnwand_gallery_ids', $gallery_ids);

        $primary_image_id = $this->resolve_primary_image_id($gallery_ids);
        if ($primary_image_id > 0) {
            set_post_thumbnail($post_id, $primary_image_id);
            update_post_meta($post_id, 'pinnwand_primary_image_id', $primary_image_id);
        } elseif (has_post_thumbnail($post_id)) {
            delete_post_thumbnail($post_id);
            delete_post_meta($post_id, 'pinnwand_primary_image_id');
        }

        return array('success' => true, 'message' => '');
    }

    public function get_gallery_ids(int $post_id): array {
        $ids = get_post_meta($post_id, 'pinnwand_gallery_ids', true);
        if (!is_array($ids)) {
            $ids = array();
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
    }

    private function get_remove_image_ids(): array {
        if (!isset($_POST['remove_images']) || !is_array($_POST['remove_images'])) {
            return array();
        }

        return array_values(array_unique(array_map('intval', wp_unslash($_POST['remove_images']))));
    }

    private function remove_images(array $gallery_ids, array $remove_ids): array {
        if (empty($remove_ids)) {
            return $gallery_ids;
        }

        $remaining = array();
        foreach ($gallery_ids as $id) {
            if (in_array($id, $remove_ids, true)) {
                wp_delete_attachment($id, true);
                continue;
            }

            $remaining[] = $id;
        }

        return $remaining;
    }

    private function handle_new_uploads(int $post_id, int $max_single_bytes, int $max_total_bytes, array $settings, int $max_new_files): array {
        if (!isset($_FILES['images']) || !is_array($_FILES['images'])) {
            return array('success' => true, 'attachment_ids' => array());
        }

        $files = $_FILES['images'];
        $names = $files['name'] ?? array();
        if (!is_array($names) || empty($names)) {
            return array('success' => true, 'attachment_ids' => array());
        }

        $requested_uploads = 0;
        foreach ($names as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            $tmp_name = (string) ($files['tmp_name'][$index] ?? '');
            $safe_name = sanitize_file_name((string) $name);

            if ($safe_name === '' && $tmp_name === '') {
                continue;
            }
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $requested_uploads++;
        }

        if ($requested_uploads <= 0) {
            return array('success' => true, 'attachment_ids' => array());
        }

        if ($max_new_files <= 0 || $requested_uploads > $max_new_files) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %d remaining upload slots */
                    __('Es koennen noch %d Bilder hochgeladen werden.', 'pinnwand'),
                    max(0, $max_new_files)
                ),
            );
        }

        $total_size = 0;
        $allowed = array_map('strval', (array) ($settings['allowed_mimes'] ?? array()));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_ids = array();
        $original_files = $_FILES;

        foreach ($names as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            $tmp_name = (string) ($files['tmp_name'][$index] ?? '');
            $size = (int) ($files['size'][$index] ?? 0);
            $safe_name = sanitize_file_name((string) $name);

            // Some browsers submit empty upload slots during edit flows; ignore them safely.
            if ($safe_name === '' && $tmp_name === '') {
                continue;
            }

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                return array(
                    'success' => false,
                    'message' => $this->build_upload_error_message($error),
                );
            }

            $type = (string) ($files['type'][$index] ?? '');

            $fileinfo = wp_check_filetype_and_ext($tmp_name, $safe_name);
            $ext = isset($fileinfo['ext']) ? strtolower((string) $fileinfo['ext']) : '';
            $real_type = isset($fileinfo['type']) ? (string) $fileinfo['type'] : '';

            $mime_allowed = in_array($type, $allowed, true) || in_array($real_type, $allowed, true);
            $ext_allowed = in_array($ext, $allowed_extensions, true);
            if (!$mime_allowed && !$ext_allowed) {
                return array(
                    'success' => false,
                    'message' => __('Dateityp nicht erlaubt. Erlaubt: jpg, jpeg, png, webp.', 'pinnwand'),
                );
            }

            if ($size > $max_single_bytes) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        /* translators: %d max MB */
                        __('Ein Bild ueberschreitet das Limit von %d MB.', 'pinnwand'),
                        (int) $settings['max_image_mb']
                    ),
                );
            }

            $total_size += $size;
            if ($total_size > $max_total_bytes) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        /* translators: %d total MB */
                        __('Gesamtlimit von %d MB fuer Bilder ueberschritten.', 'pinnwand'),
                        (int) $settings['max_total_image_mb']
                    ),
                );
            }

            $file_array = array(
                'name' => $safe_name,
                'type' => $type,
                'tmp_name' => $tmp_name,
                'error' => $error,
                'size' => $size,
            );

            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attachment_id)) {
                $_FILES = $original_files;
                return array(
                    'success' => false,
                    'message' => sprintf(
                        /* translators: %s error message */
                        __('Bild konnte nicht gespeichert werden: %s', 'pinnwand'),
                        $attachment_id->get_error_message()
                    ),
                );
            }

            $this->optimize_attachment_image((int) $attachment_id, $settings);
            $attachment_ids[] = (int) $attachment_id;
        }

        $_FILES = $original_files;

        return array('success' => true, 'attachment_ids' => $attachment_ids);
    }

    private function build_upload_error_message(int $error): string {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            $ini_limit = (string) ini_get('upload_max_filesize');
            $post_limit = (string) ini_get('post_max_size');
            return sprintf(
                /* translators: 1 upload_max_filesize, 2 post_max_size */
                __('Upload abgelehnt: Datei zu gross (Serverlimit). upload_max_filesize=%1$s, post_max_size=%2$s.', 'pinnwand'),
                $ini_limit,
                $post_limit
            );
        }

        if ($error === UPLOAD_ERR_PARTIAL) {
            return __('Upload unvollstaendig. Bitte erneut versuchen.', 'pinnwand');
        }

        if ($error === UPLOAD_ERR_NO_TMP_DIR) {
            return __('Serverfehler: temporaerer Upload-Ordner fehlt.', 'pinnwand');
        }

        if ($error === UPLOAD_ERR_CANT_WRITE) {
            return __('Serverfehler: Datei konnte nicht geschrieben werden.', 'pinnwand');
        }

        if ($error === UPLOAD_ERR_EXTENSION) {
            return __('Upload durch eine PHP-Erweiterung gestoppt.', 'pinnwand');
        }

        return __('Upload-Fehler bei einem Bild.', 'pinnwand');
    }

    private function resolve_primary_image_id(array $gallery_ids): int {
        $requested = isset($_POST['primary_image']) ? (int) $_POST['primary_image'] : 0;
        if ($requested > 0 && in_array($requested, $gallery_ids, true)) {
            return $requested;
        }

        return !empty($gallery_ids) ? (int) $gallery_ids[0] : 0;
    }

    private function optimize_attachment_image(int $attachment_id, array $settings): void {
        $path = get_attached_file($attachment_id);
        if (!$path || !file_exists($path)) {
            return;
        }

        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor)) {
            return;
        }

        $size = $editor->get_size();
        if (!is_array($size)) {
            return;
        }

        $max_width = (int) $settings['image_max_width'];
        $max_height = (int) $settings['image_max_height'];
        $current_width = isset($size['width']) ? (int) $size['width'] : 0;
        $current_height = isset($size['height']) ? (int) $size['height'] : 0;

        // Avoid unnecessary re-encoding when the image already fits limits.
        if ($current_width > 0 && $current_height > 0 && $current_width <= $max_width && $current_height <= $max_height) {
            return;
        }

        $editor->set_quality((int) $settings['image_quality']);
        $editor->resize($max_width, $max_height, false);
        $saved = $editor->save($path);
        if (is_wp_error($saved)) {
            return;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $path);
        if (!is_wp_error($metadata) && is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }
}
