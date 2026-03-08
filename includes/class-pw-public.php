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
        $style_path = PINNWAND_PLUGIN_DIR . 'public/css/pinnwand-public.css';
        if (!file_exists($style_path)) {
            return;
        }

        wp_enqueue_style(
            'pinnwand-public',
            PINNWAND_PLUGIN_URL . 'public/css/pinnwand-public.css',
            array(),
            PINNWAND_VERSION
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
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte anmelden.', 'pinnwand')), 401);
        }

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        if (mb_strlen($term) < 1) {
            wp_send_json_success(array('items' => array()));
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
        }

        wp_send_json_success(array('items' => array_values(array_map('strval', $tags))));
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

    public function validate_registration_code(WP_Error $errors, string $sanitized_user_login, string $user_email): WP_Error {
        unset($sanitized_user_login, $user_email);

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

    public function handle_successful_registration(int $user_id): void {
        unset($user_id);
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
        echo '<li><strong>' . esc_html__('Status:', 'pinnwand') . '</strong> ' . esc_html($this->translate_status($status)) . '</li>';
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

        ?>
        <script>
            (function () {
                const mainImage = document.getElementById('pw-main-image');
                const buttons = document.querySelectorAll('.pinnwand-thumb-btn');
                const prevButton = document.getElementById('pw-detail-prev');
                const nextButton = document.getElementById('pw-detail-next');
                if (!mainImage || !buttons.length) {
                    return;
                }

                function setActiveByIndex(index) {
                    const count = buttons.length;
                    if (count <= 0) {
                        return;
                    }
                    const normalized = ((index % count) + count) % count;
                    const active = buttons[normalized];
                    const src = active.getAttribute('data-main-src');
                    if (!src) {
                        return;
                    }
                    mainImage.setAttribute('src', src);
                    buttons.forEach(function (other) {
                        other.classList.remove('is-active');
                    });
                    active.classList.add('is-active');
                }

                function getActiveIndex() {
                    let activeIndex = 0;
                    buttons.forEach(function (btn, idx) {
                        if (btn.classList.contains('is-active')) {
                            activeIndex = idx;
                        }
                    });
                    return activeIndex;
                }

                buttons.forEach(function (btn, idx) {
                    btn.addEventListener('click', function () {
                        setActiveByIndex(idx);
                    });
                });

                if (prevButton) {
                    prevButton.addEventListener('click', function () {
                        setActiveByIndex(getActiveIndex() - 1);
                    });
                }
                if (nextButton) {
                    nextButton.addEventListener('click', function () {
                        setActiveByIndex(getActiveIndex() + 1);
                    });
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowLeft') {
                        setActiveByIndex(getActiveIndex() - 1);
                    } else if (event.key === 'ArrowRight') {
                        setActiveByIndex(getActiveIndex() + 1);
                    }
                });
            })();
        </script>
        <?php

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
        $offer_type = $post ? (string) get_post_meta($post->ID, 'pw_offer_type', true) : 'verleih';
        if (!in_array($offer_type, array('verkauf', 'verleih'), true)) {
            $offer_type = 'verleih';
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
                        <p>
                            <label for="pinnwand-title"><?php esc_html_e('Titel', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label><br />
                            <input id="pinnwand-title" type="text" name="title" required value="<?php echo esc_attr($title); ?>" />
                        </p>

                        <p>
                            <label for="pinnwand-description"><?php esc_html_e('Beschreibung', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label><br />
                            <textarea id="pinnwand-description" name="description" rows="6" required><?php echo esc_textarea($description); ?></textarea>
                        </p>

                        <p>
                            <label for="pinnwand-offer-type"><?php esc_html_e('Inseratetyp', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label><br />
                            <select id="pinnwand-offer-type" name="offer_type" required>
                                <option value="verleih" <?php selected($offer_type, 'verleih'); ?>><?php esc_html_e('Zu verleihen', 'pinnwand'); ?></option>
                                <option value="verkauf" <?php selected($offer_type, 'verkauf'); ?>><?php esc_html_e('Zu verkaufen', 'pinnwand'); ?></option>
                            </select>
                        </p>

                        <p>
                            <label for="pinnwand-category"><?php esc_html_e('Kategorie', 'pinnwand'); ?> <span class="pinnwand-required">*</span></label><br />
                            <select id="pinnwand-category" name="category" required>
                                <option value=""><?php esc_html_e('Bitte waehlen', 'pinnwand'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($selected_category, (int) $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label for="pinnwand-tags"><?php esc_html_e('Keywords (Komma-getrennt)', 'pinnwand'); ?></label><br />
                            <div class="pinnwand-tag-input-wrap">
                                <input id="pinnwand-tags" type="text" name="tags" value="<?php echo esc_attr($tag_value); ?>" autocomplete="off" />
                                <div id="pinnwand-tag-live-suggestions" class="pinnwand-tag-live-suggestions" hidden></div>
                            </div>
                        </p>

                        <p>
                            <label for="pinnwand-price"><?php esc_html_e('Preis / Gebuehr', 'pinnwand'); ?></label><br />
                            <input id="pinnwand-price" type="number" step="0.01" min="0" name="price" value="<?php echo esc_attr((string) $price); ?>" />
                        </p>

                        <p>
                            <label for="pinnwand-status"><?php esc_html_e('Status', 'pinnwand'); ?></label><br />
                            <select id="pinnwand-status" name="status">
                                <option value="available" <?php selected($status, 'available'); ?>><?php esc_html_e('Verfuegbar', 'pinnwand'); ?></option>
                                <option value="borrowed" <?php selected($status, 'borrowed'); ?>><?php esc_html_e('Ausgeliehen', 'pinnwand'); ?></option>
                                <option value="unavailable" <?php selected($status, 'unavailable'); ?>><?php esc_html_e('Nicht verfuegbar', 'pinnwand'); ?></option>
                            </select>
                        </p>

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
        <script>
            (function () {
                const hidden = document.getElementById('pinnwand-primary-image');
                const tagInput = document.getElementById('pinnwand-tags');
                const tagLiveBox = document.getElementById('pinnwand-tag-live-suggestions');
                const tagSuggestUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                const uploadInput = document.getElementById('pinnwand-upload-images');
                const selectedImagesInfo = document.getElementById('pinnwand-selected-images-info');

                if (uploadInput && selectedImagesInfo) {
                    const maxImages = parseInt(uploadInput.getAttribute('data-max-images') || '0', 10);
                    const existingImages = parseInt(uploadInput.getAttribute('data-existing-images') || '0', 10);
                    const remainingSlots = Math.max(0, maxImages - existingImages);
                    let bufferedFiles = null;

                    function fileKey(file) {
                        return [file.name, file.size, file.lastModified].join('__');
                    }

                    function updateSelectedInfo() {
                        const count = uploadInput.files ? uploadInput.files.length : 0;
                        if (count <= 0) {
                            selectedImagesInfo.textContent = '<?php echo esc_js(__('Keine neuen Bilder ausgewaehlt.', 'pinnwand')); ?>';
                            return;
                        }

                        const remainingAfterSelection = Math.max(0, remainingSlots - count);
                        selectedImagesInfo.textContent = count === 1
                            ? '<?php echo esc_js(__('1 neues Bild ausgewaehlt.', 'pinnwand')); ?> ' + '(<?php echo esc_js(__('verbleibend:', 'pinnwand')); ?> ' + remainingAfterSelection + ')'
                            : count + ' <?php echo esc_js(__('neue Bilder ausgewaehlt.', 'pinnwand')); ?> ' + '(<?php echo esc_js(__('verbleibend:', 'pinnwand')); ?> ' + remainingAfterSelection + ')';
                    }

                    if (remainingSlots <= 0) {
                        uploadInput.disabled = true;
                        selectedImagesInfo.textContent = '<?php echo esc_js(__('Maximale Bildanzahl erreicht. Bitte zuerst ein Bild entfernen.', 'pinnwand')); ?>';
                    }

                    uploadInput.addEventListener('change', function () {
                        const selected = uploadInput.files ? Array.from(uploadInput.files) : [];
                        if (!selected.length) {
                            updateSelectedInfo();
                            return;
                        }

                        if (typeof DataTransfer === 'undefined') {
                            updateSelectedInfo();
                            return;
                        }

                        if (!bufferedFiles) {
                            bufferedFiles = new DataTransfer();
                        }

                        const seen = new Set(Array.from(bufferedFiles.files).map(fileKey));
                        selected.forEach(function (file) {
                            if (bufferedFiles.files.length >= remainingSlots) {
                                return;
                            }
                            const key = fileKey(file);
                            if (seen.has(key)) {
                                return;
                            }
                            bufferedFiles.items.add(file);
                            seen.add(key);
                        });

                        uploadInput.files = bufferedFiles.files;
                        updateSelectedInfo();
                    });
                }

                const editMainImage = document.getElementById('pw-edit-main-image');
                const editThumbButtons = document.querySelectorAll('.pinnwand-edit-thumb-btn');
                const editPrevButton = document.getElementById('pw-edit-prev');
                const editNextButton = document.getElementById('pw-edit-next');
                function setEditActiveByIndex(index) {
                    const count = editThumbButtons.length;
                    if (!editMainImage || count <= 0) {
                        return;
                    }
                    const normalized = ((index % count) + count) % count;
                    const btn = editThumbButtons[normalized];
                    const newMainSrc = btn.getAttribute('data-main-src') || '';
                    const newThumbSrc = btn.getAttribute('data-thumb-src') || '';
                    const newAttachmentId = btn.getAttribute('data-attachment-id') || '';
                    if (!newMainSrc || !newAttachmentId) {
                        return;
                    }

                    editMainImage.setAttribute('src', newMainSrc);
                    editMainImage.setAttribute('data-main-src', newMainSrc);
                    editMainImage.setAttribute('data-thumb-src', newThumbSrc);
                    editMainImage.setAttribute('data-attachment-id', newAttachmentId);

                    editThumbButtons.forEach(function (other) {
                        other.classList.remove('is-active');
                    });
                    btn.classList.add('is-active');

                    if (hidden) {
                        hidden.value = newAttachmentId;
                    }
                }
                function getEditActiveIndex() {
                    let activeIndex = 0;
                    editThumbButtons.forEach(function (btn, idx) {
                        if (btn.classList.contains('is-active')) {
                            activeIndex = idx;
                        }
                    });
                    return activeIndex;
                }
                editThumbButtons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const index = Array.prototype.indexOf.call(editThumbButtons, btn);
                        setEditActiveByIndex(index);
                    });
                });
                if (editPrevButton) {
                    editPrevButton.addEventListener('click', function () {
                        setEditActiveByIndex(getEditActiveIndex() - 1);
                    });
                }
                if (editNextButton) {
                    editNextButton.addEventListener('click', function () {
                        setEditActiveByIndex(getEditActiveIndex() + 1);
                    });
                }
                if (editThumbButtons.length > 1) {
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'ArrowLeft') {
                            setEditActiveByIndex(getEditActiveIndex() - 1);
                        } else if (event.key === 'ArrowRight') {
                            setEditActiveByIndex(getEditActiveIndex() + 1);
                        }
                    });
                }

                if (!tagInput || !tagLiveBox) {
                    return;
                }

                let tagSuggestTimer = null;

                function getTagParts() {
                    return tagInput.value
                        .split(',')
                        .map(function (item) { return item.trim(); })
                        .filter(function (item) { return item.length > 0; });
                }

                function getCurrentToken() {
                    const raw = tagInput.value;
                    const lastComma = raw.lastIndexOf(',');
                    return (lastComma >= 0 ? raw.slice(lastComma + 1) : raw).trim();
                }

                function applySuggestion(tagValue) {
                    const raw = tagInput.value;
                    const lastComma = raw.lastIndexOf(',');
                    const baseRaw = lastComma >= 0 ? raw.slice(0, lastComma) : '';
                    const baseParts = baseRaw
                        .split(',')
                        .map(function (item) { return item.trim(); })
                        .filter(function (item) { return item.length > 0; });

                    const normalized = tagValue.toLowerCase();
                    const exists = baseParts.some(function (item) {
                        return item.toLowerCase() === normalized;
                    });
                    if (!exists) {
                        baseParts.push(tagValue);
                    }

                    tagInput.value = baseParts.join(', ') + ', ';
                    tagLiveBox.innerHTML = '';
                    tagLiveBox.hidden = true;
                    tagInput.focus();
                }

                function renderLiveSuggestions(items) {
                    if (!Array.isArray(items) || !items.length) {
                        tagLiveBox.innerHTML = '';
                        tagLiveBox.hidden = true;
                        return;
                    }

                    const selected = getTagParts().map(function (item) { return item.toLowerCase(); });
                    const filtered = items.filter(function (item) {
                        return selected.indexOf(String(item).toLowerCase()) === -1;
                    });
                    if (!filtered.length) {
                        tagLiveBox.innerHTML = '';
                        tagLiveBox.hidden = true;
                        return;
                    }

                    tagLiveBox.innerHTML = '';
                    filtered.forEach(function (item) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'pinnwand-tag-live-item';
                        btn.textContent = String(item);
                        btn.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            applySuggestion(String(item));
                        });
                        tagLiveBox.appendChild(btn);
                    });
                    tagLiveBox.hidden = false;
                }

                async function requestTagSuggestions(token) {
                    try {
                        const url = tagSuggestUrl + '?action=pinnwand_tag_suggestions&term=' + encodeURIComponent(token);
                        const response = await fetch(url, { credentials: 'same-origin' });
                        if (!response.ok) {
                            renderLiveSuggestions([]);
                            return;
                        }
                        const data = await response.json();
                        const items = data && data.success && data.data && Array.isArray(data.data.items) ? data.data.items : [];
                        renderLiveSuggestions(items);
                    } catch (e) {
                        renderLiveSuggestions([]);
                    }
                }

                tagInput.addEventListener('input', function () {
                    const token = getCurrentToken();
                    if (token.length < 2) {
                        renderLiveSuggestions([]);
                        return;
                    }
                    if (tagSuggestTimer) {
                        window.clearTimeout(tagSuggestTimer);
                    }
                    tagSuggestTimer = window.setTimeout(function () {
                        requestTagSuggestions(token);
                    }, 140);
                });

                tagInput.addEventListener('blur', function () {
                    window.setTimeout(function () {
                        tagLiveBox.hidden = true;
                    }, 120);
                });

                tagLiveBox.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
            })();
        </script>
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
                'post_type' => 'pw_artikel',
                'author' => get_current_user_id(),
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
                'posts_per_page' => 50,
            )
        );

        ob_start();
        echo $this->render_request_notice();

        if (empty($posts)) {
            echo '<p>' . esc_html__('Noch keine Artikel vorhanden.', 'pinnwand') . '</p>';
            return (string) ob_get_clean();
        }

        echo '<table class="pinnwand-dashboard-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Titel', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Status', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Datum', 'pinnwand') . '</th>';
        echo '<th>' . esc_html__('Aktionen', 'pinnwand') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($posts as $post) {
            $status = (string) get_post_meta($post->ID, 'pw_status', true);
            if ($status === '') {
                $status = 'available';
            }

            $edit_url = add_query_arg('article_id', (string) $post->ID, get_permalink());

            $delete_url = wp_nonce_url(
                admin_url('admin-post.php?action=pinnwand_delete_article&post_id=' . (int) $post->ID . '&redirect_url=' . rawurlencode(get_permalink())),
                'pinnwand_delete_article',
                'pinnwand_delete_nonce'
            );

            echo '<tr>';
            echo '<td><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a></td>';
            echo '<td>' . esc_html($this->translate_status($status)) . '</td>';
            echo '<td>' . esc_html(mysql2date(get_option('date_format'), $post->post_date)) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($edit_url) . '">' . esc_html__('Bearbeiten', 'pinnwand') . '</a> | ';
            echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Artikel wirklich loeschen?', 'pinnwand')) . '\');">' . esc_html__('Loeschen', 'pinnwand') . '</a>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">';
            echo '<input type="hidden" name="action" value="pinnwand_change_status" />';
            echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post->ID) . '" />';
            echo '<input type="hidden" name="redirect_url" value="' . esc_url(get_permalink()) . '" />';
            wp_nonce_field('pinnwand_change_status', 'pinnwand_status_nonce');
            echo '<select name="status">';
            echo '<option value="available"' . selected($status, 'available', false) . '>' . esc_html__('Verfuegbar', 'pinnwand') . '</option>';
            echo '<option value="borrowed"' . selected($status, 'borrowed', false) . '>' . esc_html__('Ausgeliehen', 'pinnwand') . '</option>';
            echo '<option value="unavailable"' . selected($status, 'unavailable', false) . '>' . esc_html__('Nicht verfuegbar', 'pinnwand') . '</option>';
            echo '</select> ';
            echo '<button type="submit">' . esc_html__('Status speichern', 'pinnwand') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        return (string) ob_get_clean();
    }

    public function render_search_form(): string {
        $keyword = isset($_GET['pw_keyword']) ? sanitize_text_field(wp_unslash($_GET['pw_keyword'])) : '';
        $offer_type = isset($_GET['pw_offer_type']) ? sanitize_key(wp_unslash($_GET['pw_offer_type'])) : '';
        if (!in_array($offer_type, array('', 'verkauf', 'verleih'), true)) {
            $offer_type = '';
        }
        $show_borrowed = isset($_GET['pw_show_borrowed']) && $_GET['pw_show_borrowed'] === '1';
        $paged = max(1, (int) ($_GET['pw_page'] ?? 1));
        $settings = PW_Settings::get();
        $title_max_length = (int) ($settings['card_title_max_length'] ?? 40);

        $query = $this->build_search_query($keyword, 0, $show_borrowed, $offer_type, $paged);

        ob_start();
        ?>
        <form method="get" class="pinnwand-search-form" id="pinnwand-search-form">
            <p class="pinnwand-search-item pinnwand-search-item-keyword">
                <label for="pw-keyword"><?php esc_html_e('Suche', 'pinnwand'); ?></label><br />
                <input id="pw-keyword" type="text" name="pw_keyword" value="<?php echo esc_attr($keyword); ?>" placeholder="<?php echo esc_attr__('Suche nach Titel, Beschreibung und Keywords', 'pinnwand'); ?>" />
            </p>

            <p class="pinnwand-search-item pinnwand-search-item-offer-type">
                <label for="pw-offer-type"><?php esc_html_e('Inseratetyp', 'pinnwand'); ?></label><br />
                <select id="pw-offer-type" name="pw_offer_type">
                    <option value="" <?php selected($offer_type, ''); ?>><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                    <option value="verleih" <?php selected($offer_type, 'verleih'); ?>><?php esc_html_e('Zu verleihen', 'pinnwand'); ?></option>
                    <option value="verkauf" <?php selected($offer_type, 'verkauf'); ?>><?php esc_html_e('Zu verkaufen', 'pinnwand'); ?></option>
                </select>
            </p>

            <p class="pinnwand-search-item pinnwand-search-item-borrowed">
                <label>
                    <input type="checkbox" name="pw_show_borrowed" value="1" <?php checked($show_borrowed); ?> />
                    <?php esc_html_e('Ausgeliehene anzeigen', 'pinnwand'); ?>
                </label>
            </p>

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
                        $cats = wp_get_post_terms($post_id, 'pw_kategorie', array('fields' => 'names'));
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
                            <p class="pinnwand-card-meta"><strong><?php esc_html_e('Status:', 'pinnwand'); ?></strong> <?php echo esc_html($this->translate_status($status)); ?></p>
                            <p class="pinnwand-card-meta"><strong><?php esc_html_e('Kategorie:', 'pinnwand'); ?></strong> <?php echo esc_html(!empty($cats) ? implode(', ', is_array($cats) ? $cats : array()) : '-'); ?></p>
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
        <script>
            (function () {
                const form = document.getElementById('pinnwand-search-form');
                const input = document.getElementById('pw-keyword');
                if (!form || !input) {
                    return;
                }
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        form.submit();
                    }
                });

                const keywordButtons = document.querySelectorAll('.pinnwand-keyword-link');
                keywordButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        const keyword = button.getAttribute('data-tag') || '';
                        input.value = keyword;
                        form.submit();
                    });
                });
            })();
        </script>
        <?php
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

    private function build_search_query(string $keyword, int $category, bool $show_borrowed, string $offer_type, int $paged): WP_Query {
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

        if (in_array($offer_type, array('verkauf', 'verleih'), true)) {
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

        if ($category > 0) {
            $base_args['tax_query'] = array(
                array(
                    'taxonomy' => 'pw_kategorie',
                    'field' => 'term_id',
                    'terms' => array($category),
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

            if ($category > 0) {
                $tax_query[] = array(
                    'taxonomy' => 'pw_kategorie',
                    'field' => 'term_id',
                    'terms' => array($category),
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
        if ($status === 'borrowed') {
            return __('Ausgeliehen', 'pinnwand');
        }

        if ($status === 'unavailable') {
            return __('Nicht verfuegbar', 'pinnwand');
        }

        return __('Verfuegbar', 'pinnwand');
    }

    private function translate_offer_type(string $offer_type): string {
        if ($offer_type === 'verkauf') {
            return __('Zu verkaufen', 'pinnwand');
        }

        return __('Zu verleihen', 'pinnwand');
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

        return '';
    }
}
