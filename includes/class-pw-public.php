<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Public {
    private PW_Media_Manager $media_manager;

    public function __construct() {
        $this->media_manager = new PW_Media_Manager();
    }

    public function register_shortcodes(): void {
        add_shortcode('pw_article_form', array($this, 'render_article_form'));
        add_shortcode('pw_user_dashboard', array($this, 'render_user_dashboard'));
        add_shortcode('pw_search_form', array($this, 'render_search_form'));
        add_shortcode('pw_profile_form', array($this, 'render_profile_form'));
    }

    public function protect_single_article(): void {
        if (!is_singular('pw_artikel') || is_user_logged_in()) {
            return;
        }

        $login_url = wp_login_url(get_permalink());
        PW_Security::safe_redirect($login_url, wp_login_url());
    }

    public function restrict_backend_for_board_users(): void {
        if (!is_user_logged_in() || current_user_can('manage_options')) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        global $pagenow;
        $allowed_pages = array('admin-post.php', 'admin-ajax.php', 'async-upload.php');
        if (in_array((string) $pagenow, $allowed_pages, true)) {
            return;
        }

        PW_Security::safe_redirect(home_url('/'));
    }

    public function hide_admin_bar_for_board_users(bool $show): bool {
        if (!is_user_logged_in()) {
            return $show;
        }

        if (current_user_can('manage_options')) {
            return $show;
        }

        return false;
    }

    public function redirect_non_admin_after_login(string $redirect_to, string $requested_redirect_to, $user): string {
        if (!$user instanceof WP_User || user_can($user, 'manage_options')) {
            return $redirect_to;
        }

        $requested_redirect_to = trim($requested_redirect_to);
        if ($requested_redirect_to !== '' && strpos($requested_redirect_to, admin_url()) !== 0) {
            return $requested_redirect_to;
        }

        return home_url('/');
    }

    public function enqueue_assets(): void {
        wp_enqueue_style('dashicons');

        $style_path = PINNWAND_PLUGIN_DIR . 'public/css/pinnwand-public.css';
        if (file_exists($style_path)) {
            wp_enqueue_style(
                'pinnwand-public',
                PINNWAND_PLUGIN_URL . 'public/css/pinnwand-public.css',
                array('dashicons'),
                PINNWAND_VERSION
            );
        }

        $gallery_script_path = PINNWAND_PLUGIN_DIR . 'public/js/pinnwand-gallery.js';
        if (file_exists($gallery_script_path)) {
            wp_enqueue_script(
                'pinnwand-gallery',
                PINNWAND_PLUGIN_URL . 'public/js/pinnwand-gallery.js',
                array(),
                PINNWAND_VERSION,
                true
            );
        }

        wp_register_script(
            'pinnwand-search-form',
            PINNWAND_PLUGIN_URL . 'public/js/pinnwand-search-form.js',
            array(),
            PINNWAND_VERSION,
            true
        );
    }

    public function filter_single_featured_image(string $html, int $post_id, int $thumbnail_id, $size, $attr): string {
        if (is_admin()) {
            return $html;
        }

        if (is_singular('pw_artikel') && get_post_type($post_id) === 'pw_artikel') {
            return '';
        }

        return $html;
    }

    public function ajax_tag_suggestions(): void {
        check_ajax_referer('pinnwand_tag_suggestions', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte anmelden.', 'pinnwand')), 401);
            return;
        }

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        if (mb_strlen($term) < 1) {
            wp_send_json_success(array('items' => array()));
            return;
        }

        $tags = get_terms(
            array(
                'taxonomy' => 'pw_tag',
                'hide_empty' => false,
                'search' => $term,
                'orderby' => 'name',
                'order' => 'ASC',
                'number' => 8,
                'fields' => 'names',
            )
        );

        if (is_wp_error($tags) || !is_array($tags)) {
            wp_send_json_success(array('items' => array()));
            return;
        }

        wp_send_json_success(array('items' => array_values(array_map('strval', $tags))));
        return;
    }

    public function render_registration_code_field(): void {
        $settings = PW_Settings::get();
        $entered = isset($_POST['pinnwand_invitation_code']) ? sanitize_text_field(wp_unslash((string) $_POST['pinnwand_invitation_code'])) : '';
        if ($entered === '' && isset($_GET['invite'])) {
            $entered = strtoupper(trim(sanitize_text_field(wp_unslash((string) $_GET['invite']))));
        }
        $captcha_enabled = (int) ($settings['registration_captcha_enabled'] ?? 0) === 1;
        $captcha_site_key = (string) ($settings['registration_captcha_site_key'] ?? '');

        echo '<p>';
        echo '<label for="pinnwand-invitation-code">' . esc_html__('Einladungscode', 'pinnwand') . '<br />';
        echo '<input type="text" name="pinnwand_invitation_code" id="pinnwand-invitation-code" class="input" value="' . esc_attr($entered) . '" size="25" /></label>';
        echo '</p>';

        if ($captcha_enabled && $captcha_site_key !== '') {
            echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($captcha_site_key) . '"></div>';
            echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
        }
    }

    public function validate_registration_code(WP_Error $errors, string $_sanitized_user_login, string $_user_email): WP_Error {

        $entered = isset($_POST['pinnwand_invitation_code']) ? strtoupper(trim(sanitize_text_field(wp_unslash((string) $_POST['pinnwand_invitation_code'])))) : '';
        $settings = PW_Settings::get();
        $configured_code = strtoupper(trim((string) ($settings['invitation_code'] ?? '')));
        $valid_until = (string) ($settings['invitation_valid_until'] ?? '');

        if ($configured_code === '' || $valid_until === '') {
            $errors->add('pinnwand_invitation_unavailable', __('Registrierung ist derzeit nicht verfuegbar.', 'pinnwand'));
            return $errors;
        }

        if ($entered === '' || $entered !== $configured_code) {
            $errors->add('pinnwand_invitation_invalid', __('Einladungscode ungueltig.', 'pinnwand'));
            return $errors;
        }

        if (!$this->is_invitation_date_valid($valid_until)) {
            $errors->add('pinnwand_invitation_expired', __('Einladungscode ist nicht gueltig (Datum).', 'pinnwand'));
        }

        if (!$this->is_registration_captcha_valid()) {
            $errors->add('pinnwand_captcha_invalid', __('Captcha-Pruefung fehlgeschlagen.', 'pinnwand'));
        }

        return $errors;
    }

    public function handle_successful_registration(int $_user_id): void {
        $entered = isset($_POST['pinnwand_invitation_code']) ? strtoupper(trim(sanitize_text_field(wp_unslash((string) $_POST['pinnwand_invitation_code'])))) : '';
        if ($entered === '') {
            return;
        }

        $settings = PW_Settings::get();
        $configured_code = strtoupper(trim((string) ($settings['invitation_code'] ?? '')));
        $valid_until = (string) ($settings['invitation_valid_until'] ?? '');
        if ($configured_code === '' || $valid_until === '') {
            return;
        }

        if ($entered === $configured_code && $this->is_invitation_date_valid($valid_until)) {
            PW_Settings::increment_invitation_usage_count();
        }
    }

    public function append_single_details(string $content): string {
        if (!is_singular('pw_artikel') || !in_the_loop() || !is_main_query() || !is_user_logged_in()) {
            return $content;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        $status = (string) get_post_meta($post_id, 'pw_status', true);
        if ($status === '') {
            $status = 'available';
        }
        $offer_type = (string) get_post_meta($post_id, 'pw_offer_type', true);
        if ($offer_type === '') {
            $offer_type = 'verleih';
        }

        $price = (float) get_post_meta($post_id, 'pw_price', true);
        $categories = wp_get_post_terms($post_id, 'pw_kategorie', array('fields' => 'names'));
        if (is_wp_error($categories) || !is_array($categories)) {
            $categories = array();
        }
        $tags = wp_get_post_terms($post_id, 'pw_tag', array('fields' => 'names'));
        if (is_wp_error($tags) || !is_array($tags)) {
            $tags = array();
        }
        $author_id = (int) get_post_field('post_author', $post_id);
        $author = get_userdata($author_id);
        $author_email = $author ? (string) $author->user_email : '';

        $gallery_ids = $this->media_manager->get_gallery_ids($post_id);
        $primary_image_id = (int) get_post_meta($post_id, 'pinnwand_primary_image_id', true);
        if ($primary_image_id <= 0 && has_post_thumbnail($post_id)) {
            $primary_image_id = (int) get_post_thumbnail_id($post_id);
        }
        if ($primary_image_id <= 0 && !empty($gallery_ids)) {
            $primary_image_id = (int) $gallery_ids[0];
        }
        if ($primary_image_id > 0) {
            $gallery_ids = array_values(array_unique(array_merge(array($primary_image_id), $gallery_ids)));
        }

        $main_image_url = $primary_image_id > 0 ? wp_get_attachment_image_url($primary_image_id, 'large') : '';
        if (!$main_image_url) {
            $main_image_url = '';
        }

        ob_start();
        echo '<section class="pinnwand-detail-layout">';
        echo '<div class="pinnwand-detail-left">';
        echo '<h3>' . esc_html__('Details', 'pinnwand') . '</h3>';
        echo '<div class="pinnwand-detail-description">' . wp_kses_post($content) . '</div>';
        echo '<ul class="pinnwand-meta-list">';
        echo '<li><strong>' . esc_html__('Inseratetyp:', 'pinnwand') . '</strong> ' . esc_html($this->translate_offer_type($offer_type)) . '</li>';
        if (PW_Settings::is_verleih_type($offer_type)) {
            echo '<li><strong>' . esc_html__('Verleih:', 'pinnwand') . '</strong> ' . esc_html($this->translate_status($status)) . '</li>';
        }
        echo '<li><strong>' . esc_html__('Kategorie:', 'pinnwand') . '</strong> ' . esc_html(!empty($categories) ? implode(', ', $categories) : '-') . '</li>';
        echo '<li><strong>' . esc_html__('Keywords:', 'pinnwand') . '</strong> ' . esc_html(!empty($tags) ? implode(', ', $tags) : '-') . '</li>';
        echo '<li><strong>' . esc_html__('Preis / Gebuehr:', 'pinnwand') . '</strong> ' . esc_html(number_format_i18n($price, 2)) . '</li>';
        echo '</ul>';

        echo '<h4>' . esc_html__('Kontakt', 'pinnwand') . '</h4>';
        echo '<ul class="pinnwand-meta-list">';
        echo '<li><strong>' . esc_html__('Name:', 'pinnwand') . '</strong> ' . esc_html(trim((string) get_user_meta($author_id, 'first_name', true) . ' ' . (string) get_user_meta($author_id, 'last_name', true))) . '</li>';
        if ($author_email !== '') {
            echo '<li><strong>' . esc_html__('E-Mail:', 'pinnwand') . '</strong> <a href="mailto:' . esc_attr(antispambot($author_email)) . '">' . esc_html(antispambot($author_email)) . '</a></li>';
        }
        echo '<li><strong>' . esc_html__('Telefon:', 'pinnwand') . '</strong> ' . esc_html((string) get_user_meta($author_id, 'pw_phone', true)) . '</li>';
        echo '<li><strong>' . esc_html__('Adresse:', 'pinnwand') . '</strong> ' . esc_html((string) get_user_meta($author_id, 'pw_address', true)) . ', ' . esc_html((string) get_user_meta($author_id, 'pw_zip', true)) . ' ' . esc_html((string) get_user_meta($author_id, 'pw_city', true)) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="pinnwand-detail-right">';
        if ($main_image_url !== '') {
            echo '<div class="pinnwand-main-image-wrap">';
            if (count($gallery_ids) > 1) {
                echo '<button type="button" id="pw-detail-prev" class="pinnwand-image-nav-btn pinnwand-image-nav-prev" aria-label="' . esc_attr__('Vorheriges Bild', 'pinnwand') . '">&#8249;</button>';
            }
            echo '<img id="pw-main-image" class="pinnwand-main-image" src="' . esc_url($main_image_url) . '" alt="' . esc_attr__('Artikelbild', 'pinnwand') . '" />';
            if (count($gallery_ids) > 1) {
                echo '<button type="button" id="pw-detail-next" class="pinnwand-image-nav-btn pinnwand-image-nav-next" aria-label="' . esc_attr__('Naechstes Bild', 'pinnwand') . '">&#8250;</button>';
            }
            echo '</div>';

            if (count($gallery_ids) > 1) {
                echo '<div class="pinnwand-thumb-row">';
                foreach ($gallery_ids as $attachment_id) {
                    $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                    $large = wp_get_attachment_image_url($attachment_id, 'large');
                    if (!$thumb || !$large) {
                        continue;
                    }

                    $is_active = ((int) $attachment_id === $primary_image_id) ? ' is-active' : '';
                    echo '<button type="button" class="pinnwand-thumb-btn' . esc_attr($is_active) . '" data-main-src="' . esc_url($large) . '">';
                    echo '<img src="' . esc_url($thumb) . '" alt="' . esc_attr__('Vorschaubild', 'pinnwand') . '" />';
                    echo '</button>';
                }
                echo '</div>';
            }
        } else {
            echo '<div class="pinnwand-no-image">' . esc_html__('Kein Bild', 'pinnwand') . '</div>';
        }
        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    public function render_article_form(): string {
        if (!is_user_logged_in()) {
            return $this->render_login_notice();
        }
        if (!$this->can_manage_articles()) {
            return '<div class="pinnwand-error">' . esc_html__('Keine Berechtigung zum Erfassen oder Bearbeiten von Artikeln.', 'pinnwand') . '</div>';
        }

        $post_id = isset($_GET['article_id']) ? (int) $_GET['article_id'] : 0;
        $post = null;

        if ($post_id > 0) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'pw_artikel' || !PW_Security::can_edit_article($post_id)) {
                return '<div class="pinnwand-error">' . esc_html__('Keine Berechtigung fuer diesen Artikel.', 'pinnwand') . '</div>';
            }
        }

        if ($post_id <= 0 && !$this->is_profile_complete(get_current_user_id())) {
            $profile_url = $this->get_profile_page_url();
            return '<div class="pinnwand-notice pinnwand-notice-error">' .
                esc_html__('Bitte Profil vollstaendig ausfuellen. Diese Angaben werden fuer die Kontaktaufnahme benoetigt.', 'pinnwand') .
                ' <a href="' . esc_url($profile_url) . '">' . esc_html__('Zum Profil', 'pinnwand') . '</a></div>';
        }

        $title = $post ? $post->post_title : '';
        $description = $post ? $post->post_content : '';
        $selected_category = 0;
        if ($post) {
            $term_ids = wp_get_post_terms($post->ID, 'pw_kategorie', array('fields' => 'ids'));
            if (!is_wp_error($term_ids) && !empty($term_ids)) {
                $selected_category = (int) $term_ids[0];
            }
        }
        $status = $post ? (string) get_post_meta($post->ID, 'pw_status', true) : 'available';
        if ($status === '') {
            $status = 'available';
        }
        $offer_types     = PW_Settings::get_offer_types();
        $offer_type_keys = array_column($offer_types, 'key');
        $default_type    = !empty($offer_type_keys) ? $offer_type_keys[0] : 'verleih';
        $offer_type      = $post ? (string) get_post_meta($post->ID, 'pw_offer_type', true) : $default_type;
        if (!in_array($offer_type, $offer_type_keys, true)) {
            $offer_type = $default_type;
        }
        $price = $post ? (float) get_post_meta($post->ID, 'pw_price', true) : 0.0;

        $categories = get_terms(
            array(
                'taxonomy' => 'pw_kategorie',
                'hide_empty' => false,
            )
        );
        if (is_wp_error($categories)) {
            $categories = array();
        }

        $tags = $post ? wp_get_post_terms($post->ID, 'pw_tag', array('fields' => 'names')) : array();
        if (!is_array($tags)) {
            $tags = array();
        }
        $tag_value = implode(', ', $tags);
        $existing_tags = get_terms(
            array(
                'taxonomy' => 'pw_tag',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
                'number' => 100,
                'fields' => 'names',
            )
        );
        if (is_wp_error($existing_tags) || !is_array($existing_tags)) {
            $existing_tags = array();
        }

        $gallery_ids = $post ? $this->media_manager->get_gallery_ids((int) $post->ID) : array();
        $gallery_ids = array_values(array_unique(array_map('intval', $gallery_ids)));
        $primary_image_id = $post ? (int) get_post_meta((int) $post->ID, 'pinnwand_primary_image_id', true) : 0;
        if ($primary_image_id <= 0 && !empty($gallery_ids)) {
            $primary_image_id = (int) $gallery_ids[0];
        }

        $settings = PW_Settings::get();
        $notice = $this->render_request_notice();
        $max_images = (int) $settings['max_images'];
        $existing_image_count = count($gallery_ids);

        wp_enqueue_script(
            'pinnwand-article-form',
            PINNWAND_PLUGIN_URL . 'public/js/pinnwand-article-form.js',
            array(),
            PINNWAND_VERSION,
            true
        );
        wp_localize_script(
            'pinnwand-article-form',
            'pinnwandFormL10n',
            array(
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'ajaxNonce' => wp_create_nonce('pinnwand_tag_suggestions'),
                'noImages'  => __('Keine neuen Bilder ausgewaehlt.', 'pinnwand'),
                'oneImage'  => __('1 neues Bild ausgewaehlt.', 'pinnwand'),
                'manyImages' => __('neue Bilder ausgewaehlt.', 'pinnwand'),
                'remaining' => __('verbleibend:', 'pinnwand'),
                'maxReached' => __('Maximale Bildanzahl erreicht. Bitte zuerst ein Bild entfernen.', 'pinnwand'),
            )
        );

        $edit_main_image_id = $primary_image_id > 0 ? $primary_image_id : (!empty($gallery_ids) ? (int) $gallery_ids[0] : 0);
        $edit_main_image_url = $edit_main_image_id > 0 ? wp_get_attachment_image_url($edit_main_image_id, 'large') : '';
        $edit_main_thumb_url = $edit_main_image_id > 0 ? wp_get_attachment_image_url($edit_main_image_id, 'thumbnail') : '';
        $edit_preview_ids = $gallery_ids;

        ob_start();
        ?>
        <div class="pinnwand-form-wrap">
            <?php echo $notice; ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pinnwand-article-form">
                <input type="hidden" name="action" id="pinnwand-form-action" value="pinnwand_save_article" />
                <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                <input type="hidden" name="redirect_url" value="<?php echo esc_url(add_query_arg('article_id', (string) $post_id, get_permalink())); ?>" />
                <input type="hidden" name="primary_image" id="pinnwand-primary-image" value="<?php echo esc_attr((string) $primary_image_id); ?>" />
                <?php wp_nonce_field('pinnwand_save_article', 'pinnwand_article_nonce'); ?>
                <?php wp_nonce_field('pinnwand_remove_image', 'pinnwand_remove_image_nonce'); ?>
                <?php wp_nonce_field('pinnwand_set_primary_image', 'pinnwand_set_primary_image_nonce'); ?>
                <?php wp_nonce_field('pinnwand_upload_image', 'pinnwand_upload_image_nonce'); ?>

                <div class="pinnwand-edit-layout">
                    <div class="pinnwand-edit-left">
                        <p class="pinnwand-form-field">
                            <label for="pinnwand-offer-type"><?php esc_html_e('Inseratetyp', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <select id="pinnwand-offer-type" name="offer_type" required>
                                <?php foreach ($offer_types as $ot) : ?>
                                    <option value="<?php echo esc_attr($ot['key']); ?>" <?php selected($offer_type, $ot['key']); ?> data-verleih="<?php echo !empty($ot['verleih_moeglich']) ? '1' : '0'; ?>"><?php echo esc_html($ot['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p class="pinnwand-form-field">
                            <label for="pinnwand-title"><?php esc_html_e('Titel', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pinnwand-title" type="text" name="title" required value="<?php echo esc_attr($title); ?>" />
                        </p>

                        <p class="pinnwand-form-field pinnwand-form-field-textarea">
                            <label for="pinnwand-description"><?php esc_html_e('Beschreibung', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <textarea id="pinnwand-description" name="description" rows="6" required><?php echo esc_textarea($description); ?></textarea>
                        </p>

                        <p class="pinnwand-form-field">
                            <label for="pinnwand-category"><?php esc_html_e('Kategorie', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <select id="pinnwand-category" name="category" required>
                                <option value=""><?php esc_html_e('Bitte waehlen', 'pinnwand'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($selected_category, (int) $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p class="pinnwand-form-field">
                            <label for="pinnwand-tags"><?php esc_html_e('Keywords', 'pinnwand'); ?></label>
                            <div class="pinnwand-tag-input-wrap">
                                <input id="pinnwand-tags" type="text" name="tags" value="<?php echo esc_attr($tag_value); ?>" autocomplete="off" />
                                <div id="pinnwand-tag-live-suggestions" class="pinnwand-tag-live-suggestions" hidden></div>
                            </div>
                        </p>

                        <p class="pinnwand-form-field">
                            <label for="pinnwand-price"><?php esc_html_e('Preis / Gebuehr', 'pinnwand'); ?></label>
                            <input id="pinnwand-price" type="number" step="0.01" min="0" name="price" value="<?php echo esc_attr((string) $price); ?>" />
                        </p>

                        <p id="pinnwand-status-field" class="pinnwand-form-field">
                            <label for="pinnwand-status"><?php esc_html_e('Verleih', 'pinnwand'); ?></label>
                            <select id="pinnwand-status" name="status">
                                <option value="available" <?php selected($status, 'available'); ?>><?php esc_html_e('Verfuegbar', 'pinnwand'); ?></option>
                                <option value="borrowed" <?php selected($status, 'borrowed'); ?>><?php esc_html_e('Ausgeliehen', 'pinnwand'); ?></option>
                                <option value="unavailable" <?php selected($status, 'unavailable'); ?>><?php esc_html_e('Nicht verfuegbar', 'pinnwand'); ?></option>
                            </select>
                        </p>
                        <script>
                        (function() {
                            var sel = document.getElementById('pinnwand-offer-type');
                            var field = document.getElementById('pinnwand-status-field');
                            function toggle() {
                                var opt = sel.options[sel.selectedIndex];
                                field.style.display = (opt && opt.dataset.verleih === '1') ? '' : 'none';
                            }
                            sel.addEventListener('change', toggle);
                            toggle();
                        })();
                        </script>

                    </div>

                    <aside class="pinnwand-edit-right">
                        <?php if ($edit_main_image_url) : ?>
                            <div class="pinnwand-main-image-wrap">
                                <?php if (count($edit_preview_ids) > 1) : ?>
                                    <button type="button" id="pw-edit-prev" class="pinnwand-image-nav-btn pinnwand-image-nav-prev" aria-label="<?php esc_attr_e('Vorheriges Bild', 'pinnwand'); ?>">&#8249;</button>
                                <?php endif; ?>
                                <img
                                    id="pw-edit-main-image"
                                    class="pinnwand-main-image"
                                    src="<?php echo esc_url($edit_main_image_url); ?>"
                                    data-main-src="<?php echo esc_url($edit_main_image_url); ?>"
                                    data-thumb-src="<?php echo esc_url($edit_main_thumb_url); ?>"
                                    data-attachment-id="<?php echo esc_attr((string) $edit_main_image_id); ?>"
                                    alt="<?php esc_attr_e('Artikelbild', 'pinnwand'); ?>"
                                />
                                <?php if (count($edit_preview_ids) > 1) : ?>
                                    <button type="button" id="pw-edit-next" class="pinnwand-image-nav-btn pinnwand-image-nav-next" aria-label="<?php esc_attr_e('Naechstes Bild', 'pinnwand'); ?>">&#8250;</button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="pinnwand-no-image"><?php esc_html_e('Kein Bild', 'pinnwand'); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($edit_preview_ids)) : ?>
                            <div class="pinnwand-edit-image-grid">
                                <?php foreach ($edit_preview_ids as $image_id) : ?>
                                    <?php
                                    $thumb = wp_get_attachment_image_url($image_id, 'thumbnail');
                                    $large = wp_get_attachment_image_url($image_id, 'large');
                                    if (!$thumb || !$large) {
                                        continue;
                                    }
                                    ?>
                                    <div class="pinnwand-edit-image-card">
                                        <button
                                            type="button"
                                            class="pinnwand-thumb-btn pinnwand-edit-thumb-btn<?php echo ((int) $image_id === (int) $edit_main_image_id) ? ' is-active' : ''; ?>"
                                            data-main-src="<?php echo esc_url($large); ?>"
                                            data-thumb-src="<?php echo esc_url($thumb); ?>"
                                            data-attachment-id="<?php echo esc_attr((string) $image_id); ?>"
                                        >
                                            <img src="<?php echo esc_url($thumb); ?>" alt="<?php esc_attr_e('Vorschaubild', 'pinnwand'); ?>" />
                                        </button>
                                        <div class="pinnwand-image-control-actions">
                                            <button
                                                type="submit"
                                                formnovalidate
                                                class="pinnwand-primary-icon-btn<?php echo ((int) $image_id === (int) $primary_image_id) ? ' is-active' : ''; ?>"
                                                title="<?php esc_attr_e('Als Hauptbild markieren', 'pinnwand'); ?>"
                                                aria-label="<?php esc_attr_e('Als Hauptbild markieren', 'pinnwand'); ?>"
                                                onclick="document.getElementById('pinnwand-form-action').value='pinnwand_set_primary_image';document.getElementById('pinnwand-image-action-id').value='<?php echo esc_js((string) $image_id); ?>';"
                                            >
                                                <span aria-hidden="true">&#9733;</span>
                                            </button>
                                            <button
                                                type="submit"
                                                formnovalidate
                                                class="pinnwand-remove-icon-btn"
                                                title="<?php esc_attr_e('Bild entfernen', 'pinnwand'); ?>"
                                                aria-label="<?php esc_attr_e('Bild entfernen', 'pinnwand'); ?>"
                                                onclick="document.getElementById('pinnwand-form-action').value='pinnwand_remove_image';document.getElementById('pinnwand-image-action-id').value='<?php echo esc_js((string) $image_id); ?>';"
                                            >
                                                <span aria-hidden="true">&#128465;</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="pinnwand-single-upload">
                            <label for="pinnwand-upload-images"><?php esc_html_e('Bilder hochladen', 'pinnwand'); ?></label><br />
                            <small>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        __('Maximal %1$d Bilder, %2$d MB pro Bild, %3$d MB gesamt.', 'pinnwand'),
                                        (int) $settings['max_images'],
                                        (int) $settings['max_image_mb'],
                                        (int) $settings['max_total_image_mb']
                                    )
                                );
                                ?>
                            </small>
                            <input
                                id="pinnwand-upload-images"
                                type="file"
                                name="images[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                data-max-images="<?php echo esc_attr((string) $max_images); ?>"
                                data-existing-images="<?php echo esc_attr((string) $existing_image_count); ?>"
                            />
                            <small id="pinnwand-selected-images-info"><?php esc_html_e('Keine neuen Bilder ausgewaehlt.', 'pinnwand'); ?></small>
                        </div>
                    </aside>
                </div>

                <input type="hidden" name="image_id" id="pinnwand-image-action-id" value="" />

                <p>
                    <button type="submit" onclick="document.getElementById('pinnwand-form-action').value='pinnwand_save_article';document.getElementById('pinnwand-image-action-id').value='';"><?php echo esc_html($post ? __('Artikel aktualisieren', 'pinnwand') : __('Artikel erstellen', 'pinnwand')); ?></button>
                </p>
            </form>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function render_user_dashboard(): string {
        if (!is_user_logged_in()) {
            return $this->render_login_notice();
        }
        if (!$this->can_manage_articles()) {
            return '<div class="pinnwand-error">' . esc_html__('Keine Berechtigung fuer das Dashboard.', 'pinnwand') . '</div>';
        }

        $edit_article_id = isset($_GET['article_id']) ? (int) $_GET['article_id'] : 0;
        if ($edit_article_id > 0) {
            $post = get_post($edit_article_id);
            if ($post && $post->post_type === 'pw_artikel' && PW_Security::can_edit_article($edit_article_id)) {
                $back_url = remove_query_arg(array('article_id', 'pw_action', 'error_message'));
                $out = '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('Zurueck zur Uebersicht', 'pinnwand') . '</a></p>';
                $out .= $this->render_article_form();
                return $out;
            }
        }

        $posts = get_posts(
            array(
                'post_type'      => 'pw_artikel',
                'author'         => get_current_user_id(),
                'post_status'    => array('publish', 'draft'),
                'orderby'        => 'date',
                'order'          => 'DESC',
                'posts_per_page' => 50,
            )
        );

        ob_start();
        echo '<div class="pinnwand-wrap">';
        echo $this->render_request_notice();

        if (empty($posts)) {
            echo '<p>' . esc_html__('Noch keine Artikel vorhanden.', 'pinnwand') . '</p>';
            echo '</div>';
            return (string) ob_get_clean();
        }

        $icon_edit   = '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';
        $icon_delete = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
        $icon_hide   = '<span class="dashicons dashicons-hidden" aria-hidden="true"></span>';
        $icon_show   = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
        $icon_borrow = '<span class="dashicons dashicons-lock" aria-hidden="true"></span>';
        $icon_return = '<span class="dashicons dashicons-unlock" aria-hidden="true"></span>';

        echo '<table class="pinnwand-dashboard-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Typ', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Titel', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Verleih', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Anzeige', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Datum', 'pinnwand') . '</th>';
        echo '<th class="pw-col-actions">' . esc_html__('Aktionen', 'pinnwand') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($posts as $post) {
            $pw_status  = (string) get_post_meta($post->ID, 'pw_status', true);
            if ($pw_status === '') {
                $pw_status = 'available';
            }
            $offer_type       = (string) get_post_meta($post->ID, 'pw_offer_type', true);
            $is_draft         = $post->post_status === 'draft';
            $toggle_label     = $is_draft ? __('Aktivieren', 'pinnwand') : __('Deaktivieren', 'pinnwand');
            $toggle_icon      = $is_draft ? $icon_show : $icon_hide;
            $visibility_label = $is_draft ? __('Inaktiv', 'pinnwand') : __('Aktiv', 'pinnwand');

            $edit_url   = add_query_arg('article_id', (string) $post->ID, get_permalink());
            $delete_url = wp_nonce_url(
                admin_url('admin-post.php?action=pinnwand_delete_article&post_id=' . (int) $post->ID . '&redirect_url=' . rawurlencode(get_permalink())),
                'pinnwand_delete_article',
                'pinnwand_delete_nonce'
            );

            echo '<tr' . ($is_draft ? ' class="pw-row-inactive"' : '') . '>';

            echo '<td>' . esc_html($this->translate_offer_type($offer_type)) . '</td>';

            echo '<td><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a></td>';

            echo '<td><span class="pw-badge pw-badge-' . esc_attr($pw_status) . '">' . esc_html($this->translate_status($pw_status)) . '</span></td>';

            echo '<td>' . esc_html($visibility_label) . '</td>';

            echo '<td>' . esc_html(mysql2date('d.m.y', $post->post_date)) . '</td>';

            $toggle_url = wp_nonce_url(
                admin_url('admin-post.php?action=pinnwand_toggle_visibility&post_id=' . (int) $post->ID . '&redirect_url=' . rawurlencode(get_permalink())),
                'pinnwand_toggle_visibility',
                'pinnwand_toggle_visibility_nonce'
            );

            echo '<td class="pw-col-actions"><div class="pw-actions">';
            if (PW_Settings::is_verleih_type($offer_type)) {
                $is_borrowed       = $pw_status === 'borrowed';
                $borrow_new_status = $is_borrowed ? 'available' : 'borrowed';
                $borrow_title      = $is_borrowed ? __('Als verfuegbar markieren', 'pinnwand') : __('Als ausgeliehen markieren', 'pinnwand');
                $borrow_icon       = $is_borrowed ? $icon_return : $icon_borrow;
                $borrow_url        = wp_nonce_url(
                    admin_url('admin-post.php?action=pinnwand_change_status&post_id=' . (int) $post->ID . '&status=' . $borrow_new_status . '&redirect_url=' . rawurlencode(get_permalink())),
                    'pinnwand_change_status',
                    'pinnwand_status_nonce'
                );
                echo '<a href="' . esc_url($borrow_url) . '" class="pw-icon-btn" title="' . esc_attr($borrow_title) . '">' . $borrow_icon . '</a>';
            }
            echo '<a href="' . esc_url($toggle_url) . '" class="pw-icon-btn" title="' . esc_attr($toggle_label) . '">' . $toggle_icon . '</a>';
            echo '<a href="' . esc_url($edit_url) . '" class="pw-icon-btn" title="' . esc_attr__('Bearbeiten', 'pinnwand') . '">' . $icon_edit . '</a>';
            echo '<a href="' . esc_url($delete_url) . '" class="pw-icon-btn" title="' . esc_attr__('Loeschen', 'pinnwand') . '" onclick="return confirm(\'' . esc_js(__('Artikel wirklich loeschen?', 'pinnwand')) . '\');">' . $icon_delete . '</a>';
            echo '</div></td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    public function render_search_form(array $atts = array()): string {
        $atts            = shortcode_atts(array('offer_type' => ''), $atts, 'pw_search_form');
        $offer_types     = PW_Settings::get_offer_types();
        $offer_type_keys = array_column($offer_types, 'key');
        $locked_offer_type = in_array($atts['offer_type'], $offer_type_keys, true) ? $atts['offer_type'] : '';

        $keyword       = isset($_GET['pw_keyword'])  ? sanitize_text_field(wp_unslash($_GET['pw_keyword'])) : '';
        $category_slug = isset($_GET['pw_category']) ? sanitize_key(wp_unslash($_GET['pw_category']))        : '';

        $all_categories_flat = get_terms(array('taxonomy' => 'pw_kategorie', 'hide_empty' => true, 'orderby' => 'name'));
        if (is_wp_error($all_categories_flat)) {
            $all_categories_flat = array();
        }
        $all_categories = $this->sort_terms_hierarchically($all_categories_flat);

        if ($locked_offer_type !== '') {
            $offer_type = $locked_offer_type;
        } else {
            $offer_type = isset($_GET['pw_offer_type']) ? sanitize_key(wp_unslash($_GET['pw_offer_type'])) : '';
            if ($offer_type !== '' && !in_array($offer_type, $offer_type_keys, true)) {
                $offer_type = '';
            }
        }

        $has_verleih = $locked_offer_type !== ''
            ? PW_Settings::is_verleih_type($locked_offer_type)
            : !empty(array_filter($offer_types, static fn($ot) => !empty($ot['verleih_moeglich'])));

        $show_borrowed = $has_verleih
            && isset($_GET['pw_show_borrowed'])
            && $_GET['pw_show_borrowed'] === '1';

        $paged = max(1, (int) ($_GET['pw_page'] ?? 1));
        $settings = PW_Settings::get();
        $title_max_length = (int) ($settings['card_title_max_length'] ?? 40);

        $query = $this->build_search_query($keyword, $category_slug, $show_borrowed, $offer_type, $paged);

        ob_start();
        ?>
        <form method="get" class="pinnwand-search-form" id="pinnwand-search-form">
            <?php if ($locked_offer_type !== '') : ?>
                <input type="hidden" name="pw_offer_type" value="<?php echo esc_attr($locked_offer_type); ?>" />
            <?php endif; ?>

            <p class="pinnwand-search-item pinnwand-search-item-keyword">
                <label for="pw-keyword"><?php esc_html_e('Suche', 'pinnwand'); ?></label>
                <input id="pw-keyword" type="text" name="pw_keyword" value="<?php echo esc_attr($keyword); ?>" placeholder="<?php echo esc_attr__('Suche nach Titel, Beschreibung und Keywords', 'pinnwand'); ?>" />
            </p>

            <?php if (!empty($all_categories)) : ?>
                <p class="pinnwand-search-item pinnwand-search-item-category">
                    <label for="pw-category"><?php esc_html_e('Kategorie', 'pinnwand'); ?></label>
                    <select id="pw-category" name="pw_category">
                        <option value=""><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                        <?php foreach ($all_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat['term']->slug); ?>" <?php selected($category_slug, $cat['term']->slug); ?>><?php echo esc_html(str_repeat('— ', $cat['depth']) . $cat['term']->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endif; ?>

            <?php if ($locked_offer_type === '') : ?>
                <p class="pinnwand-search-item pinnwand-search-item-offer-type">
                    <label for="pw-offer-type"><?php esc_html_e('Inseratetyp', 'pinnwand'); ?></label>
                    <select id="pw-offer-type" name="pw_offer_type">
                        <option value="" <?php selected($offer_type, ''); ?>><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                        <?php foreach ($offer_types as $ot) : ?>
                            <option value="<?php echo esc_attr($ot['key']); ?>" <?php selected($offer_type, $ot['key']); ?>><?php echo esc_html($ot['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endif; ?>

            <?php if ($has_verleih) : ?>
                <p class="pinnwand-search-item pinnwand-search-item-borrowed">
                    <label>
                        <input type="checkbox" name="pw_show_borrowed" value="1" <?php checked($show_borrowed); ?> />
                        <?php esc_html_e('Ausgeliehene anzeigen', 'pinnwand'); ?>
                    </label>
                </p>
            <?php endif; ?>

            <p class="pinnwand-search-item pinnwand-search-item-submit">
                <button type="submit"><?php esc_html_e('Suchen', 'pinnwand'); ?></button>
            </p>
        </form>

        <div class="pinnwand-results">
            <?php if (!$query->have_posts()) : ?>
                <p><?php esc_html_e('Keine Artikel gefunden.', 'pinnwand'); ?></p>
            <?php else : ?>
                <div class="pinnwand-card-grid">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <?php
                        $post_id = get_the_ID();
                        $status = (string) get_post_meta($post_id, 'pw_status', true);
                        if ($status === '') {
                            $status = 'available';
                        }
                        $card_offer_type = (string) get_post_meta($post_id, 'pw_offer_type', true);
                        if ($card_offer_type === '') {
                            $card_offer_type = 'verleih';
                        }
                        $cats = wp_get_post_terms($post_id, 'pw_kategorie');
                        $tags = wp_get_post_terms($post_id, 'pw_tag', array('fields' => 'names'));
                        $full_title = $this->normalize_title_text(get_the_title($post_id));
                        $display_title = $this->truncate_title($full_title, $title_max_length);
                        ?>
                        <article class="pinnwand-card">
                            <a href="<?php the_permalink(); ?>" class="pinnwand-card-image-link">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('medium'); ?>
                                <?php else : ?>
                                    <div class="pinnwand-no-image"><?php esc_html_e('Kein Bild', 'pinnwand'); ?></div>
                                <?php endif; ?>
                            </a>
                            <h3 class="pinnwand-card-title"><a href="<?php the_permalink(); ?>" title="<?php echo esc_attr($full_title); ?>"><?php echo esc_html($display_title); ?></a></h3>
                            <p class="pinnwand-card-meta"><strong><?php esc_html_e('Inseratetyp:', 'pinnwand'); ?></strong> <?php echo esc_html($this->translate_offer_type($card_offer_type)); ?></p>
                            <?php if (PW_Settings::is_verleih_type($card_offer_type)) : ?>
                            <p class="pinnwand-card-meta"><strong><?php esc_html_e('Verleih:', 'pinnwand'); ?></strong> <span class="pw-badge pw-badge-<?php echo esc_attr($status ?: 'available'); ?>"><?php echo esc_html($this->translate_status($status)); ?></span></p>
                            <?php endif; ?>
                            <p class="pinnwand-card-meta">
                                <strong><?php esc_html_e('Kategorie:', 'pinnwand'); ?></strong>
                                <?php if (!empty($cats) && is_array($cats) && !is_wp_error($cats)) : ?>
                                    <?php foreach ($cats as $cat) : ?>
                                        <button type="button" class="pinnwand-category-link" data-category="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?></button>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </p>
                            <p class="pinnwand-card-meta">
                                <strong><?php esc_html_e('Keywords:', 'pinnwand'); ?></strong>
                                <?php if (!empty($tags) && is_array($tags)) : ?>
                                    <?php
                                    $visible_tags = array_slice($tags, 0, 3);
                                    $hidden_tags = array_slice($tags, 3);
                                    ?>
                                    <?php foreach ($visible_tags as $tag_name) : ?>
                                        <button type="button" class="pinnwand-keyword-link" data-tag="<?php echo esc_attr((string) $tag_name); ?>">
                                            <?php echo esc_html((string) $tag_name); ?>
                                        </button>
                                    <?php endforeach; ?>
                                    <?php if (!empty($hidden_tags)) : ?>
                                        <details class="pinnwand-keyword-more">
                                            <summary>+<?php echo esc_html((string) count($hidden_tags)); ?></summary>
                                            <div class="pinnwand-keyword-more-list">
                                                <?php foreach ($hidden_tags as $tag_name) : ?>
                                                    <button type="button" class="pinnwand-keyword-link" data-tag="<?php echo esc_attr((string) $tag_name); ?>">
                                                        <?php echo esc_html((string) $tag_name); ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php if ((int) $query->max_num_pages > 1) : ?>
                    <div class="pinnwand-pagination">
                        <?php if ($paged > 1) : ?>
                            <a href="<?php echo esc_url(add_query_arg('pw_page', (string) ($paged - 1))); ?>"><?php esc_html_e('Zurueck', 'pinnwand'); ?></a>
                        <?php endif; ?>
                        <span><?php echo esc_html(sprintf(__('Seite %1$d von %2$d', 'pinnwand'), $paged, (int) $query->max_num_pages)); ?></span>
                        <?php if ($paged < (int) $query->max_num_pages) : ?>
                            <a href="<?php echo esc_url(add_query_arg('pw_page', (string) ($paged + 1))); ?>"><?php esc_html_e('Weiter', 'pinnwand'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        wp_enqueue_script('pinnwand-search-form');
        wp_reset_postdata();

        return (string) ob_get_clean();
    }

    public function render_profile_form(): string {
        if (!is_user_logged_in()) {
            return $this->render_login_notice();
        }

        $user_id = get_current_user_id();

        $first_name = (string) get_user_meta($user_id, 'first_name', true);
        $last_name = (string) get_user_meta($user_id, 'last_name', true);
        $email = (string) wp_get_current_user()->user_email;
        $phone = (string) get_user_meta($user_id, 'pw_phone', true);
        $address = (string) get_user_meta($user_id, 'pw_address', true);
        $zip = (string) get_user_meta($user_id, 'pw_zip', true);
        $city = (string) get_user_meta($user_id, 'pw_city', true);
        $notice = $this->render_request_notice();

        ob_start();
        ?>
        <div class="pinnwand-profile-wrap">
            <?php echo $notice; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pinnwand-profile-form">
                <input type="hidden" name="action" value="pinnwand_save_profile" />
                <input type="hidden" name="redirect_url" value="<?php echo esc_url(get_permalink()); ?>" />
                <?php wp_nonce_field('pinnwand_save_profile', 'pinnwand_profile_nonce'); ?>

                <div class="pinnwand-profile-grid">
                    <div class="pinnwand-profile-row pinnwand-profile-row-two">
                        <div class="pinnwand-profile-field">
                            <label for="pw-first-name"><?php esc_html_e('Vorname', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pw-first-name" type="text" name="first_name" required value="<?php echo esc_attr($first_name); ?>" />
                        </div>
                        <div class="pinnwand-profile-field">
                            <label for="pw-last-name"><?php esc_html_e('Nachname', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pw-last-name" type="text" name="last_name" required value="<?php echo esc_attr($last_name); ?>" />
                        </div>
                    </div>

                    <div class="pinnwand-profile-row pinnwand-profile-row-two">
                        <div class="pinnwand-profile-field pinnwand-profile-field-phone">
                            <label for="pw-phone"><?php esc_html_e('Telefon', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pw-phone" type="text" name="phone" required value="<?php echo esc_attr($phone); ?>" />
                        </div>
                        <div class="pinnwand-profile-field pinnwand-profile-field-email">
                            <label for="pw-email"><?php esc_html_e('E-Mail', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pw-email" class="pinnwand-readonly-input" type="email" name="email" readonly value="<?php echo esc_attr($email); ?>" />
                        </div>
                    </div>

                    <div class="pinnwand-profile-field">
                        <label for="pw-address"><?php esc_html_e('Adresse', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                        <input id="pw-address" type="text" name="address" required value="<?php echo esc_attr($address); ?>" />
                    </div>

                    <div class="pinnwand-profile-row pinnwand-profile-row-zip-city">
                        <div class="pinnwand-profile-field pinnwand-profile-field-zip">
                            <label for="pw-zip"><?php esc_html_e('PLZ', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pw-zip" type="text" name="zip" required value="<?php echo esc_attr($zip); ?>" />
                        </div>
                        <div class="pinnwand-profile-field">
                            <label for="pw-city"><?php esc_html_e('Ort', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label>
                            <input id="pw-city" type="text" name="city" required value="<?php echo esc_attr($city); ?>" />
                        </div>
                    </div>

                    <div class="pinnwand-profile-submit">
                        <button type="submit"><?php esc_html_e('Profil speichern', 'pinnwand'); ?></button>
                    </div>
                </div>
            </form>

            <div class="pinnwand-profile-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="pinnwand_export_profile" />
                    <?php wp_nonce_field('pinnwand_export_profile', 'pinnwand_export_nonce'); ?>
                    <button type="submit"><?php esc_html_e('Meine Daten exportieren', 'pinnwand'); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Profil inkl. Artikel wirklich loeschen?', 'pinnwand')); ?>');">
                    <input type="hidden" name="action" value="pinnwand_delete_profile" />
                    <?php wp_nonce_field('pinnwand_delete_profile', 'pinnwand_delete_profile_nonce'); ?>
                    <button type="submit" class="button-link-delete"><?php esc_html_e('Profil loeschen', 'pinnwand'); ?></button>
                </form>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function build_search_query(string $keyword, string $category_slug, bool $show_borrowed, string $offer_type, int $paged): WP_Query {
        $sort_args = $this->get_listing_sort_args();
        $statuses = array('available');
        if ($show_borrowed) {
            $statuses[] = 'borrowed';
        }

        $meta_query = array(
            'relation' => 'AND',
            array(
                'relation' => 'OR',
                array(
                    'key' => 'pw_status',
                    'value' => $statuses,
                    'compare' => 'IN',
                ),
                array(
                    'key' => 'pw_status',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        if ($offer_type !== '' && in_array($offer_type, PW_Settings::get_offer_type_keys(), true)) {
            $meta_query[] = array(
                'key' => 'pw_offer_type',
                'value' => $offer_type,
                'compare' => '=',
            );
        }

        $base_args = array(
            'post_type' => 'pw_artikel',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'orderby' => $sort_args['orderby'],
            'order' => $sort_args['order'],
            'meta_query' => $meta_query,
            'paged' => $paged,
        );

        if ($category_slug !== '') {
            $base_args['tax_query'] = array(
                array(
                    'taxonomy' => 'pw_kategorie',
                    'field' => 'slug',
                    'terms' => array($category_slug),
                ),
            );
        }

        if ($keyword === '') {
            return new WP_Query($base_args);
        }

        $text_args = $base_args;
        $text_args['fields'] = 'ids';
        $text_args['posts_per_page'] = -1;
        $text_args['s'] = $keyword;
        $text_ids = get_posts($text_args);

        $tag_term_ids = get_terms(
            array(
                'taxonomy' => 'pw_tag',
                'hide_empty' => false,
                'fields' => 'ids',
                'search' => $keyword,
            )
        );

        $tag_ids = array();
        if (!is_wp_error($tag_term_ids) && !empty($tag_term_ids)) {
            $tag_args = $base_args;
            $tag_args['fields'] = 'ids';
            $tag_args['posts_per_page'] = -1;

            $tax_query = array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'pw_tag',
                    'field' => 'term_id',
                    'terms' => array_map('intval', $tag_term_ids),
                ),
            );

            if ($category_slug !== '') {
                $tax_query[] = array(
                    'taxonomy' => 'pw_kategorie',
                    'field' => 'slug',
                    'terms' => array($category_slug),
                );
            }

            $tag_args['tax_query'] = $tax_query;
            $tag_ids = get_posts($tag_args);
        }

        $matched_ids = array_values(array_unique(array_map('intval', array_merge($text_ids, $tag_ids))));
        if (empty($matched_ids)) {
            $base_args['post__in'] = array(0);
            return new WP_Query($base_args);
        }

        $base_args['post__in'] = $matched_ids;
        unset($base_args['s']);

        return new WP_Query($base_args);
    }

    private function render_login_notice(): string {
        $url = wp_login_url(get_permalink());
        return '<p>' . sprintf(
            /* translators: %s login URL */
            esc_html__('Bitte zuerst anmelden: %s', 'pinnwand'),
            '<a href="' . esc_url($url) . '">' . esc_html__('Zum Login', 'pinnwand') . '</a>'
        ) . '</p>';
    }

    private function translate_status(string $status): string {
        $map = array(
            'borrowed'    => __('Ausgeliehen', 'pinnwand'),
            'unavailable' => __('Nicht verfuegbar', 'pinnwand'),
            'inactive'    => __('Inaktiv', 'pinnwand'),
        );
        return $map[$status] ?? __('Verfuegbar', 'pinnwand');
    }

    private function translate_offer_type(string $offer_type): string {
        foreach (PW_Settings::get_offer_types() as $type) {
            if ($type['key'] === $offer_type) {
                return $type['label'];
            }
        }
        return $offer_type;
    }

    private function can_manage_articles(): bool {
        return current_user_can('edit_pw_artikels');
    }

    private function truncate_title(string $title, int $max_length = 40): string {
        $title = $this->normalize_title_text($title);

        if ($max_length < 1) {
            return $title;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title) <= $max_length) {
                return $title;
            }
            return rtrim(mb_substr($title, 0, $max_length)) . '...';
        }

        if (strlen($title) <= $max_length) {
            return $title;
        }

        return rtrim(substr($title, 0, $max_length)) . '...';
    }

    private function normalize_title_text(string $title): string {
        $decoded = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(wp_strip_all_tags($decoded));
    }

    private function sort_terms_hierarchically(array $terms, int $parent = 0, int $depth = 0): array {
        $result = array();
        foreach ($terms as $term) {
            if ((int) $term->parent === $parent) {
                $result[] = array('term' => $term, 'depth' => $depth);
                $children = $this->sort_terms_hierarchically($terms, $term->term_id, $depth + 1);
                $result   = array_merge($result, $children);
            }
        }
        return $result;
    }

    private function get_listing_sort_args(): array {
        $settings = PW_Settings::get();
        $mode = isset($settings['listing_sort_mode']) ? sanitize_key((string) $settings['listing_sort_mode']) : 'newest';

        if ($mode === 'oldest') {
            return array('orderby' => 'date', 'order' => 'ASC');
        }
        if ($mode === 'title_asc') {
            return array('orderby' => 'title', 'order' => 'ASC');
        }
        if ($mode === 'title_desc') {
            return array('orderby' => 'title', 'order' => 'DESC');
        }

        return array('orderby' => 'date', 'order' => 'DESC');
    }

    private function is_profile_complete(int $user_id): bool {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user || !is_email((string) $user->user_email)) {
            return false;
        }

        $required_values = array(
            (string) get_user_meta($user_id, 'first_name', true),
            (string) get_user_meta($user_id, 'last_name', true),
            (string) get_user_meta($user_id, 'pw_phone', true),
            (string) get_user_meta($user_id, 'pw_address', true),
            (string) get_user_meta($user_id, 'pw_zip', true),
            (string) get_user_meta($user_id, 'pw_city', true),
        );

        foreach ($required_values as $value) {
            if (trim($value) === '') {
                return false;
            }
        }

        return preg_match('/^\d{4,5}$/', trim((string) get_user_meta($user_id, 'pw_zip', true))) === 1;
    }

    private function get_profile_page_url(): string {
        $current_id = get_queried_object_id();
        if ($current_id > 0) {
            $current = get_post($current_id);
            if ($current instanceof WP_Post && has_shortcode((string) $current->post_content, 'pw_profile_form')) {
                $url = get_permalink($current->ID);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        $pages = get_posts(
            array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => 50,
                'orderby' => 'menu_order title',
                'order' => 'ASC',
            )
        );

        foreach ($pages as $page) {
            if ($page instanceof WP_Post && has_shortcode((string) $page->post_content, 'pw_profile_form')) {
                $url = get_permalink($page->ID);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        $fallback = get_permalink();
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return home_url('/');
    }

    private function is_invitation_date_valid(string $valid_until): bool {
        $valid_until = trim($valid_until);
        if ($valid_until === '') {
            return false;
        }

        $date = DateTime::createFromFormat('Y-m-d', $valid_until);
        if (!$date) {
            return false;
        }

        $today = wp_date('Y-m-d');
        return $valid_until >= $today;
    }

    private function is_registration_captcha_valid(): bool {
        $settings = PW_Settings::get();
        $captcha_enabled = (int) ($settings['registration_captcha_enabled'] ?? 0) === 1;
        if (!$captcha_enabled) {
            return true;
        }

        $site_key = trim((string) ($settings['registration_captcha_site_key'] ?? ''));
        $secret_key = trim((string) ($settings['registration_captcha_secret_key'] ?? ''));
        if ($site_key === '' || $secret_key === '') {
            return false;
        }

        $token = isset($_POST['cf-turnstile-response']) ? trim((string) wp_unslash($_POST['cf-turnstile-response'])) : '';
        if ($token === '') {
            return false;
        }

        $body = array(
            'secret' => $secret_key,
            'response' => $token,
        );
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $body['remoteip'] = sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            array(
                'timeout' => 8,
                'body' => $body,
            )
        );
        if (is_wp_error($response)) {
            return false;
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($json) && !empty($json['success']);
    }

    private function render_request_notice(): string {
        $error_message = isset($_GET['error_message']) ? sanitize_text_field(wp_unslash($_GET['error_message'])) : '';
        if ($error_message !== '') {
            return '<div class="pinnwand-notice pinnwand-notice-error">' . esc_html($error_message) . '</div>';
        }

        $action = isset($_GET['pw_action']) ? sanitize_key(wp_unslash($_GET['pw_action'])) : '';
        if ($action === 'article_created') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Artikel wurde gespeichert.', 'pinnwand') . '</div>';
        }
        if ($action === 'article_updated') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Artikel wurde aktualisiert.', 'pinnwand') . '</div>';
        }
        if ($action === 'profile_saved') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Daten wurden erfolgreich gespeichert.', 'pinnwand') . '</div>';
        }
        if ($action === 'image_removed') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Bild wurde entfernt.', 'pinnwand') . '</div>';
        }
        if ($action === 'primary_image_set') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Hauptbild wurde gesetzt.', 'pinnwand') . '</div>';
        }
        if ($action === 'image_uploaded') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Bild wurde hochgeladen.', 'pinnwand') . '</div>';
        }
        if ($action === 'article_hidden') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Inserat wurde deaktiviert.', 'pinnwand') . '</div>';
        }
        if ($action === 'article_published') {
            return '<div class="pinnwand-notice pinnwand-notice-success">' . esc_html__('Inserat wurde wieder aktiviert.', 'pinnwand') . '</div>';
        }

        return '';
    }
}
