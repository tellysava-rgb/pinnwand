<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Settings {
    private const OPTION_KEY = 'pinnwand_settings';
    private const OFFER_TYPES_OPTION = 'pinnwand_offer_types';

    public function register_admin_menu(): void {
        add_menu_page(
            __('Pinnwand', 'pinnwand'),
            __('Pinnwand', 'pinnwand'),
            'manage_options',
            'pinnwand-all-articles',
            array($this, 'render_articles_page'),
            'dashicons-pressthis',
            26
        );

        add_filter('parent_file', function (string $parent_file): string {
            global $pagenow;
            if ($pagenow === 'edit-tags.php') {
                $tax = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
                if (in_array($tax, array('pw_kategorie', 'pw_tag'), true)) {
                    return 'pinnwand-all-articles';
                }
            }
            return $parent_file;
        });

        add_filter('submenu_file', function (?string $submenu_file): ?string {
            global $pagenow;
            if ($pagenow === 'edit-tags.php') {
                $tax = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
                if ($tax === 'pw_kategorie') {
                    return 'edit-tags.php?taxonomy=pw_kategorie&post_type=pw_artikel';
                }
                if ($tax === 'pw_tag') {
                    return 'edit-tags.php?taxonomy=pw_tag&post_type=pw_artikel';
                }
            }
            return $submenu_file;
        });

        add_submenu_page(
            'pinnwand-all-articles',
            __('Alle Artikel', 'pinnwand'),
            __('Alle Artikel', 'pinnwand'),
            'manage_options',
            'pinnwand-all-articles',
            array($this, 'render_articles_page')
        );

        remove_submenu_page('pinnwand-all-articles', 'edit.php?post_type=pw_artikel');
        remove_submenu_page('pinnwand-all-articles', 'post-new.php?post_type=pw_artikel');

        add_submenu_page(
            'pinnwand-all-articles',
            __('Inseratetypen', 'pinnwand'),
            __('Inseratetypen', 'pinnwand'),
            'manage_options',
            'pinnwand-offer-types',
            array($this, 'render_offer_types_page')
        );

        add_submenu_page(
            'pinnwand-all-articles',
            __('Kategorien', 'pinnwand'),
            __('Kategorien', 'pinnwand'),
            'manage_options',
            'edit-tags.php?taxonomy=pw_kategorie&post_type=pw_artikel'
        );

        add_submenu_page(
            'pinnwand-all-articles',
            __('Keywords', 'pinnwand'),
            __('Keywords', 'pinnwand'),
            'manage_options',
            'edit-tags.php?taxonomy=pw_tag&post_type=pw_artikel'
        );

        add_submenu_page(
            'pinnwand-all-articles',
            __('Einstellungen', 'pinnwand'),
            __('Einstellungen', 'pinnwand'),
            'manage_options',
            'pinnwand-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings(): void {
        register_setting('pinnwand_settings_group', self::OPTION_KEY, array($this, 'sanitize_settings'));
    }

    public function sanitize_settings(array $input): array {
        $defaults = self::get_defaults();
        $previous = self::get();

        $output = array();
        $output['max_images'] = max(1, min(20, (int) ($input['max_images'] ?? $defaults['max_images'])));
        $output['max_image_mb'] = max(1, min(20, (int) ($input['max_image_mb'] ?? $defaults['max_image_mb'])));
        $output['max_total_image_mb'] = max(1, min(100, (int) ($input['max_total_image_mb'] ?? $defaults['max_total_image_mb'])));
        $output['image_max_width'] = max(640, min(3840, (int) ($input['image_max_width'] ?? $defaults['image_max_width'])));
        $output['image_max_height'] = max(640, min(3840, (int) ($input['image_max_height'] ?? $defaults['image_max_height'])));
        $output['image_quality'] = max(50, min(95, (int) ($input['image_quality'] ?? $defaults['image_quality'])));
        $output['card_title_max_length'] = max(10, min(120, (int) ($input['card_title_max_length'] ?? $defaults['card_title_max_length'])));
        $allowed_sort_modes = array('newest', 'oldest', 'title_asc', 'title_desc');
        $requested_sort_mode = sanitize_key((string) ($input['listing_sort_mode'] ?? $defaults['listing_sort_mode']));
        $output['listing_sort_mode'] = in_array($requested_sort_mode, $allowed_sort_modes, true) ? $requested_sort_mode : $defaults['listing_sort_mode'];
        $output['invitation_code'] = strtoupper(trim(sanitize_text_field((string) ($input['invitation_code'] ?? ''))));
        $output['invitation_valid_until'] = $this->sanitize_date((string) ($input['invitation_valid_until'] ?? ''));
        $output['registration_captcha_enabled'] = !empty($input['registration_captcha_enabled']) ? 1 : 0;
        $output['registration_captcha_site_key'] = trim(sanitize_text_field((string) ($input['registration_captcha_site_key'] ?? '')));
        $output['registration_captcha_secret_key'] = trim(sanitize_text_field((string) ($input['registration_captcha_secret_key'] ?? '')));

        $output['rate_per_minute'] = max(1, min(120, (int) ($input['rate_per_minute'] ?? $defaults['rate_per_minute'])));
        $output['rate_per_hour'] = max(1, min(500, (int) ($input['rate_per_hour'] ?? $defaults['rate_per_hour'])));
        $output['rate_per_day'] = max(1, min(2000, (int) ($input['rate_per_day'] ?? $defaults['rate_per_day'])));

        $allowed = isset($input['allowed_mimes']) && is_array($input['allowed_mimes']) ? $input['allowed_mimes'] : $defaults['allowed_mimes'];
        $sanitized_mimes = array();
        foreach ($allowed as $mime) {
            $mime = sanitize_text_field((string) $mime);
            if (in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
                $sanitized_mimes[] = $mime;
            }
        }
        $output['allowed_mimes'] = empty($sanitized_mimes) ? $defaults['allowed_mimes'] : array_values(array_unique($sanitized_mimes));
        $output['invitation_usage_count'] = (int) ($previous['invitation_usage_count'] ?? 0);

        $previous_code = (string) ($previous['invitation_code'] ?? '');
        $previous_date = (string) ($previous['invitation_valid_until'] ?? '');
        if ($output['invitation_code'] !== $previous_code || $output['invitation_valid_until'] !== $previous_date) {
            $output['invitation_usage_count'] = 0;
        }

        return $output;
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }

        $settings = self::get();
        $invitation_code = trim((string) $settings['invitation_code']);
        $registration_url = wp_registration_url();
        if ($invitation_code !== '') {
            $registration_url = add_query_arg('invite', $invitation_code, $registration_url);
        }
        $registration_url_parts = wp_parse_url($registration_url);
        $registration_url_short = (string) ($registration_url_parts['path'] ?? '/wp-login.php');
        if (!empty($registration_url_parts['query'])) {
            $registration_url_short .= '?' . (string) $registration_url_parts['query'];
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pinnwand Einstellungen', 'pinnwand'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('pinnwand_settings_group'); ?>
                <div class="pw-settings-grid">
                    <section class="pw-settings-card pw-settings-card-images">
                        <h2><?php esc_html_e('Registrierung', 'pinnwand'); ?></h2>
                        <p class="description"><?php esc_html_e('Nur Benutzer mit gueltigem Einladungscode koennen sich registrieren.', 'pinnwand'); ?></p>
                        <div class="pw-settings-field">
                            <label for="pw-invitation-code"><?php esc_html_e('Einladungscode', 'pinnwand'); ?></label>
                            <input id="pw-invitation-code" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[invitation_code]" value="<?php echo esc_attr((string) $settings['invitation_code']); ?>" />
                        </div>
                        <div class="pw-settings-field">
                            <label for="pw-invitation-valid-until"><?php esc_html_e('Gueltig bis (Datum)', 'pinnwand'); ?></label>
                            <input id="pw-invitation-valid-until" type="date" name="<?php echo esc_attr(self::OPTION_KEY); ?>[invitation_valid_until]" value="<?php echo esc_attr((string) $settings['invitation_valid_until']); ?>" />
                        </div>
                        <div class="pw-settings-field">
                            <label><?php esc_html_e('Verwendungen aktuell', 'pinnwand'); ?></label>
                            <input type="number" value="<?php echo esc_attr((string) ((int) $settings['invitation_usage_count'])); ?>" readonly />
                        </div>
                        <p class="description pw-settings-registration-link">
                            <strong><?php esc_html_e('Hier der Registrierungslink:', 'pinnwand'); ?></strong>
                            <a class="pw-settings-registration-link-text" href="<?php echo esc_url($registration_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($registration_url_short); ?></a>
                            <button type="button" class="button-link pw-copy-registration-link" data-copy-url="<?php echo esc_attr($registration_url); ?>">
                                <?php esc_html_e('Link kopieren', 'pinnwand'); ?>
                            </button>
                        </p>
                        <p class="description"><?php esc_html_e('Regel: leer oder Vergangenheit = ungueltig, heute oder Zukunft = gueltig.', 'pinnwand'); ?></p>
                    </section>

                    <section class="pw-settings-card">
                        <h2><?php esc_html_e('Captcha', 'pinnwand'); ?></h2>
                        <div class="pw-settings-field">
                            <label for="pw-registration-captcha-enabled">
                                <input id="pw-registration-captcha-enabled" type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[registration_captcha_enabled]" value="1" <?php checked((int) $settings['registration_captcha_enabled'], 1); ?> />
                                <?php esc_html_e('Captcha bei Registrierung aktivieren (Cloudflare Turnstile)', 'pinnwand'); ?>
                            </label>
                        </div>
                        <div
                            id="pw-captcha-details"
                            class="pw-settings-captcha-details"
                            <?php if ((int) $settings['registration_captcha_enabled'] !== 1) : ?>
                                style="display:none;"
                            <?php endif; ?>
                        >
                            <h3><?php esc_html_e('Captcha (Turnstile)', 'pinnwand'); ?></h3>
                            <p class="description">
                                <?php
                                echo wp_kses(
                                    __(
                                        'Turnstile Keys erzeugen: <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">Cloudflare Dashboard</a> -> Turnstile -> Widget erstellen. Danach Site Key hier bei "Turnstile Site Key" und Secret Key bei "Turnstile Secret Key" eintragen.',
                                        'pinnwand'
                                    ),
                                    array(
                                        'a' => array(
                                            'href' => array(),
                                            'target' => array(),
                                            'rel' => array(),
                                        ),
                                    )
                                );
                                ?>
                            </p>
                            <div class="pw-settings-field">
                                <label for="pw-registration-captcha-site-key"><?php esc_html_e('Turnstile Site Key', 'pinnwand'); ?></label>
                                <input id="pw-registration-captcha-site-key" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[registration_captcha_site_key]" value="<?php echo esc_attr((string) $settings['registration_captcha_site_key']); ?>" />
                            </div>
                            <div class="pw-settings-field">
                                <label for="pw-registration-captcha-secret-key"><?php esc_html_e('Turnstile Secret Key', 'pinnwand'); ?></label>
                                <input id="pw-registration-captcha-secret-key" type="password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[registration_captcha_secret_key]" value="<?php echo esc_attr((string) $settings['registration_captcha_secret_key']); ?>" />
                            </div>
                        </div>
                    </section>

                    <section class="pw-settings-card">
                        <h2><?php esc_html_e('Anzeige', 'pinnwand'); ?></h2>
                        <p class="description"><?php esc_html_e('Einstellungen zur Anzeige.', 'pinnwand'); ?></p>
                        <div class="pw-settings-field">
                            <label for="pw-card-title-max-length"><?php esc_html_e('Maximale Titellaenge in der Pinnwand (Zeichen)', 'pinnwand'); ?></label>
                            <input id="pw-card-title-max-length" type="number" min="10" max="120" name="<?php echo esc_attr(self::OPTION_KEY); ?>[card_title_max_length]" value="<?php echo esc_attr((string) $settings['card_title_max_length']); ?>" />
                        </div>
                        <div class="pw-settings-field">
                            <label for="pw-listing-sort-mode"><?php esc_html_e('Sortierung der Artikel auf der Pinnwand', 'pinnwand'); ?></label>
                            <select id="pw-listing-sort-mode" name="<?php echo esc_attr(self::OPTION_KEY); ?>[listing_sort_mode]">
                                <option value="newest" <?php selected($settings['listing_sort_mode'], 'newest'); ?>><?php esc_html_e('Neuste zuerst', 'pinnwand'); ?></option>
                                <option value="oldest" <?php selected($settings['listing_sort_mode'], 'oldest'); ?>><?php esc_html_e('Aelteste zuerst', 'pinnwand'); ?></option>
                                <option value="title_asc" <?php selected($settings['listing_sort_mode'], 'title_asc'); ?>><?php esc_html_e('Alphabetisch A-Z', 'pinnwand'); ?></option>
                                <option value="title_desc" <?php selected($settings['listing_sort_mode'], 'title_desc'); ?>><?php esc_html_e('Alphabetisch Z-A', 'pinnwand'); ?></option>
                            </select>
                        </div>
                    </section>

                    <section class="pw-settings-card">
                        <h2><?php esc_html_e('Bilder', 'pinnwand'); ?></h2>
                        <p class="description"><?php esc_html_e('Einstellungen fuer Upload und Bildgroesse.', 'pinnwand'); ?></p>
                        <div class="pw-settings-subgrid">
                            <div>
                                <h3><?php esc_html_e('Max. Upload', 'pinnwand'); ?></h3>
                                <div class="pw-settings-field">
                                    <label for="pw-max-images"><?php esc_html_e('Max. Bilder pro Artikel', 'pinnwand'); ?></label>
                                    <input id="pw-max-images" class="pw-compact-number" type="number" min="1" max="20" name="<?php echo esc_attr(self::OPTION_KEY); ?>[max_images]" value="<?php echo esc_attr((string) $settings['max_images']); ?>" />
                                </div>
                                <div class="pw-settings-field">
                                    <label for="pw-max-image-mb"><?php esc_html_e('Max. Dateigroesse pro Bild (MB)', 'pinnwand'); ?></label>
                                    <input id="pw-max-image-mb" class="pw-compact-number" type="number" min="1" max="20" name="<?php echo esc_attr(self::OPTION_KEY); ?>[max_image_mb]" value="<?php echo esc_attr((string) $settings['max_image_mb']); ?>" />
                                </div>
                                <div class="pw-settings-field">
                                    <label for="pw-max-total-mb"><?php esc_html_e('Max. Gesamtgroesse pro Artikel (MB)', 'pinnwand'); ?></label>
                                    <input id="pw-max-total-mb" class="pw-compact-number" type="number" min="1" max="100" name="<?php echo esc_attr(self::OPTION_KEY); ?>[max_total_image_mb]" value="<?php echo esc_attr((string) $settings['max_total_image_mb']); ?>" />
                                </div>
                            </div>
                            <div>
                                <h3><?php esc_html_e('Bildergroesse', 'pinnwand'); ?></h3>
                                <div class="pw-settings-field">
                                    <label for="pw-image-width"><?php esc_html_e('Max. Bildbreite (px)', 'pinnwand'); ?></label>
                                    <input id="pw-image-width" class="pw-compact-number" type="number" min="640" max="3840" name="<?php echo esc_attr(self::OPTION_KEY); ?>[image_max_width]" value="<?php echo esc_attr((string) $settings['image_max_width']); ?>" />
                                </div>
                                <div class="pw-settings-field">
                                    <label for="pw-image-height"><?php esc_html_e('Max. Bildhoehe (px)', 'pinnwand'); ?></label>
                                    <input id="pw-image-height" class="pw-compact-number" type="number" min="640" max="3840" name="<?php echo esc_attr(self::OPTION_KEY); ?>[image_max_height]" value="<?php echo esc_attr((string) $settings['image_max_height']); ?>" />
                                </div>
                                <div class="pw-settings-field">
                                    <label for="pw-image-quality"><?php esc_html_e('Bildqualitaet (50-95)', 'pinnwand'); ?></label>
                                    <input id="pw-image-quality" class="pw-compact-number" type="number" min="50" max="95" name="<?php echo esc_attr(self::OPTION_KEY); ?>[image_quality]" value="<?php echo esc_attr((string) $settings['image_quality']); ?>" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="pw-settings-card">
                        <h2><?php esc_html_e('Limits fuer Neuerfassung', 'pinnwand'); ?></h2>
                        <p class="description"><?php esc_html_e('Um Missbrauch zu verhindern, koennen verschiedene Maximalwerte erstellt werden.', 'pinnwand'); ?></p>
                        <div class="pw-settings-subgrid-limits">
                            <div class="pw-settings-field">
                                <label for="pw-rate-minute"><?php esc_html_e('Maximal pro Minute', 'pinnwand'); ?></label>
                                <input id="pw-rate-minute" type="number" min="1" max="120" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rate_per_minute]" value="<?php echo esc_attr((string) $settings['rate_per_minute']); ?>" />
                            </div>
                            <div class="pw-settings-field">
                                <label for="pw-rate-hour"><?php esc_html_e('Maximal pro Stunde', 'pinnwand'); ?></label>
                                <input id="pw-rate-hour" type="number" min="1" max="500" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rate_per_hour]" value="<?php echo esc_attr((string) $settings['rate_per_hour']); ?>" />
                            </div>
                            <div class="pw-settings-field">
                                <label for="pw-rate-day"><?php esc_html_e('Maximal pro Tag', 'pinnwand'); ?></label>
                                <input id="pw-rate-day" type="number" min="1" max="2000" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rate_per_day]" value="<?php echo esc_attr((string) $settings['rate_per_day']); ?>" />
                            </div>
                        </div>
                    </section>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_articles_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }

        // Inseratetypen dynamisch laden.
        $offer_types     = self::get_offer_types();
        $offer_type_keys = array_column($offer_types, 'key');
        $offer_type_map  = array_column($offer_types, 'label', 'key');

        // Filter-Werte aus GET auslesen und sanitieren.
        $f_offer_type = isset($_GET['pw_f_offer_type']) ? sanitize_key(wp_unslash($_GET['pw_f_offer_type'])) : '';
        $f_title      = isset($_GET['pw_f_title'])      ? sanitize_text_field(wp_unslash($_GET['pw_f_title'])) : '';
        $f_category   = isset($_GET['pw_f_category'])   ? (int) $_GET['pw_f_category'] : 0;
        $f_status     = isset($_GET['pw_f_status'])     ? sanitize_key(wp_unslash($_GET['pw_f_status'])) : '';
        $f_username   = isset($_GET['pw_f_username'])   ? sanitize_text_field(wp_unslash($_GET['pw_f_username'])) : '';
        $f_role       = isset($_GET['pw_f_role'])       ? sanitize_key(wp_unslash($_GET['pw_f_role'])) : '';

        // WP_Query-Args aufbauen.
        $query_args = array(
            'post_type'              => 'pw_artikel',
            'post_status'            => array('publish', 'draft'),
            'posts_per_page'         => -1,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
        );

        if ($f_title !== '') {
            $query_args['s'] = $f_title;
        }

        $meta_query = array('relation' => 'AND');
        if (in_array($f_offer_type, $offer_type_keys, true)) {
            $meta_query[] = array('key' => 'pw_offer_type', 'value' => $f_offer_type, 'compare' => '=');
        }
        if (in_array($f_status, array('available', 'borrowed', 'unavailable'), true)) {
            $meta_query[] = array('key' => 'pw_status', 'value' => $f_status, 'compare' => '=');
        }
        if (count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        if ($f_category > 0) {
            $query_args['tax_query'] = array(
                array('taxonomy' => 'pw_kategorie', 'field' => 'term_id', 'terms' => array($f_category)),
            );
        }

        // Benutzer-Filter: Username und/oder Rolle — beide ergeben author__in.
        $author_filter_ids = null;
        if ($f_username !== '' || $f_role !== '') {
            $user_args = array('fields' => 'ids');
            if ($f_username !== '') {
                $user_args['search']         = '*' . $f_username . '*';
                $user_args['search_columns'] = array('user_login');
            }
            if ($f_role !== '') {
                $user_args['role'] = $f_role;
            }
            $author_filter_ids = get_users($user_args);
        }

        if ($author_filter_ids !== null) {
            $query_args['author__in'] = empty($author_filter_ids) ? array(0) : array_map('intval', $author_filter_ids);
        }

        $posts = get_posts($query_args);

        // Benutzerdaten vorab cachen.
        $author_ids = array_values(array_unique(array_map(static function ($p) {
            return (int) $p->post_author;
        }, $posts)));
        if (!empty($author_ids)) {
            get_users(array('include' => $author_ids, 'fields' => 'all'));
        }

        // Alle Kategorien fuer Filter-Dropdown.
        $all_categories = get_terms(array('taxonomy' => 'pw_kategorie', 'hide_empty' => false));
        if (is_wp_error($all_categories)) {
            $all_categories = array();
        }

        $role_labels = array(
            'administrator'   => __('Administrator', 'pinnwand'),
            'pinnwand_nutzer' => __('Pinnwand-Nutzer', 'pinnwand'),
            'editor'          => __('Redakteur', 'pinnwand'),
            'author'          => __('Autor', 'pinnwand'),
            'subscriber'      => __('Abonnent', 'pinnwand'),
        );

        $status_labels = array(
            'available'   => __('Verfuegbar', 'pinnwand'),
            'borrowed'    => __('Ausgeliehen', 'pinnwand'),
            'unavailable' => __('Nicht verfuegbar', 'pinnwand'),
        );

        $page_url = admin_url('admin.php?page=pinnwand-all-articles');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Alle Inserate', 'pinnwand'); ?></h1>

            <form method="get" action="<?php echo esc_url($page_url); ?>" class="pw-articles-filter-form" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:16px 0;">
                <input type="hidden" name="page" value="pinnwand-all-articles" />

                <div>
                    <label for="pw-f-offer-type" style="display:block;font-weight:600;margin-bottom:2px;"><?php esc_html_e('Inseratetyp', 'pinnwand'); ?></label>
                    <select id="pw-f-offer-type" name="pw_f_offer_type">
                        <option value=""><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                        <?php foreach ($offer_types as $ot) : ?>
                            <option value="<?php echo esc_attr($ot['key']); ?>" <?php selected($f_offer_type, $ot['key']); ?>><?php echo esc_html($ot['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="pw-f-title" style="display:block;font-weight:600;margin-bottom:2px;"><?php esc_html_e('Titel', 'pinnwand'); ?></label>
                    <input id="pw-f-title" type="text" name="pw_f_title" value="<?php echo esc_attr($f_title); ?>" placeholder="<?php esc_attr_e('Suche...', 'pinnwand'); ?>" style="width:160px;" />
                </div>

                <div>
                    <label for="pw-f-category" style="display:block;font-weight:600;margin-bottom:2px;"><?php esc_html_e('Kategorie', 'pinnwand'); ?></label>
                    <select id="pw-f-category" name="pw_f_category">
                        <option value="0"><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                        <?php foreach ($all_categories as $cat) : ?>
                            <option value="<?php echo esc_attr((string) $cat->term_id); ?>" <?php selected($f_category, (int) $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="pw-f-status" style="display:block;font-weight:600;margin-bottom:2px;"><?php esc_html_e('Status', 'pinnwand'); ?></label>
                    <select id="pw-f-status" name="pw_f_status">
                        <option value=""><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                        <?php foreach ($status_labels as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($f_status, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="pw-f-username" style="display:block;font-weight:600;margin-bottom:2px;"><?php esc_html_e('Benutzername', 'pinnwand'); ?></label>
                    <input id="pw-f-username" type="text" name="pw_f_username" value="<?php echo esc_attr($f_username); ?>" placeholder="<?php esc_attr_e('Suche...', 'pinnwand'); ?>" style="width:140px;" />
                </div>

                <div>
                    <label for="pw-f-role" style="display:block;font-weight:600;margin-bottom:2px;"><?php esc_html_e('Rolle', 'pinnwand'); ?></label>
                    <select id="pw-f-role" name="pw_f_role">
                        <option value=""><?php esc_html_e('Alle', 'pinnwand'); ?></option>
                        <?php foreach ($role_labels as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($f_role, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;gap:6px;align-items:center;">
                    <?php submit_button(__('Filtern', 'pinnwand'), 'secondary', 'pw_filter_submit', false); ?>
                    <?php if ($f_offer_type !== '' || $f_title !== '' || $f_category > 0 || $f_status !== '' || $f_username !== '' || $f_role !== '') : ?>
                        <a href="<?php echo esc_url($page_url); ?>" class="button"><?php esc_html_e('Zurücksetzen', 'pinnwand'); ?></a>
                    <?php endif; ?>
                </div>
            </form>

            <p class="description">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d number of articles */
                        _n('%d Inserat', '%d Inserate', count($posts), 'pinnwand'),
                        count($posts)
                    )
                );
                ?>
            </p>

            <?php if (empty($posts)) : ?>
                <p><?php esc_html_e('Keine Inserate gefunden.', 'pinnwand'); ?></p>
            <?php else : ?>
                <table class="widefat striped pw-articles-overview-table" style="margin-top:8px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Inseratetyp', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Titel', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Kategorie', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Preis / Gebuehr', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Verleih', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Anzeige', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Benutzername', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('E-Mail', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Rolle', 'pinnwand'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Aktionen', 'pinnwand'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post) :
                            $offer_type   = (string) get_post_meta($post->ID, 'pw_offer_type', true);
                            $status       = (string) get_post_meta($post->ID, 'pw_status', true);
                            $price        = (float)  get_post_meta($post->ID, 'pw_price', true);

                            $offer_type_label = $offer_type_map[$offer_type] ?? esc_html($offer_type);

                            $status_label = $status_labels[$status] ?? __('Verfuegbar', 'pinnwand');

                            $cats      = wp_get_post_terms($post->ID, 'pw_kategorie', array('fields' => 'names'));
                            $cat_label = (!is_wp_error($cats) && !empty($cats)) ? implode(', ', $cats) : '—';

                            $author     = get_userdata((int) $post->post_author);
                            $roles      = $author ? (array) $author->roles : array();
                            $role_label = '—';
                            foreach ($roles as $role) {
                                if (isset($role_labels[$role])) {
                                    $role_label = $role_labels[$role];
                                    break;
                                }
                            }

                            $permalink = get_permalink($post->ID);
                        ?>
                            <tr>
                                <td><?php echo esc_html($offer_type_label); ?></td>
                                <td>
                                    <?php if ($permalink) : ?>
                                        <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($post->post_title); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($cat_label); ?></td>
                                <td><?php echo esc_html(number_format_i18n($price, 2)); ?></td>
                                <td><?php if (PW_Settings::is_verleih_type($offer_type)) : ?><span class="pw-badge pw-badge-<?php echo esc_attr($status ?: 'available'); ?>"><?php echo esc_html($status_label); ?></span><?php else : ?>—<?php endif; ?></td>
                                <td>
                                    <?php if ($post->post_status === 'draft') : ?>
                                        <span style="color:#b32d2e;font-weight:600;"><?php esc_html_e('Inaktiv', 'pinnwand'); ?></span>
                                    <?php else : ?>
                                        <span style="color:#1e7e34;"><?php esc_html_e('Aktiv', 'pinnwand'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $author ? esc_html($author->user_login) : '—'; ?></td>
                                <td>
                                    <?php if ($author) : ?>
                                        <a href="mailto:<?php echo esc_attr(antispambot($author->user_email)); ?>">
                                            <?php echo esc_html(antispambot($author->user_email)); ?>
                                        </a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($role_label); ?></td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <span style="display:inline-flex;gap:6px;align-items:center;">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:contents;">
                                            <input type="hidden" name="action" value="pinnwand_toggle_visibility" />
                                            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post->ID); ?>" />
                                            <input type="hidden" name="redirect_url" value="<?php echo esc_url(admin_url('admin.php?page=pinnwand-all-articles')); ?>" />
                                            <?php wp_nonce_field('pinnwand_toggle_visibility', 'pinnwand_toggle_visibility_nonce'); ?>
                                            <button type="submit" class="button-link" title="<?php echo $post->post_status === 'draft' ? esc_attr__('Freischalten', 'pinnwand') : esc_attr__('Sperren', 'pinnwand'); ?>" style="cursor:pointer;line-height:1;">
                                                <span class="dashicons <?php echo $post->post_status === 'draft' ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
                                            </button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Inserat wirklich loeschen?', 'pinnwand')); ?>');" style="display:contents;">
                                            <input type="hidden" name="action" value="pinnwand_delete_article" />
                                            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post->ID); ?>" />
                                            <input type="hidden" name="redirect_url" value="<?php echo esc_attr(admin_url('admin.php?page=pinnwand-all-articles')); ?>" />
                                            <?php wp_nonce_field('pinnwand_delete_article', 'pinnwand_delete_nonce'); ?>
                                            <button type="submit" class="button-link" title="<?php esc_attr_e('Loeschen', 'pinnwand'); ?>" style="cursor:pointer;color:#b32d2e;line-height:1;">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </form>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function get(): array {
        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        return wp_parse_args($saved, self::get_defaults());
    }

    public static function get_defaults(): array {
        return array(
            'max_images' => 5,
            'max_image_mb' => 5,
            'max_total_image_mb' => 20,
            'image_max_width' => 1920,
            'image_max_height' => 1920,
            'image_quality' => 90,
            'card_title_max_length' => 40,
            'listing_sort_mode' => 'newest',
            'invitation_code' => '',
            'invitation_valid_until' => '',
            'invitation_usage_count' => 0,
            'registration_captcha_enabled' => 0,
            'registration_captcha_site_key' => '',
            'registration_captcha_secret_key' => '',
            'allowed_mimes' => array('image/jpeg', 'image/png', 'image/webp'),
            'rate_per_minute' => 1,
            'rate_per_hour' => 10,
            'rate_per_day' => 10,
        );
    }

    public static function increment_invitation_usage_count(): void {
        $settings = self::get();
        $settings['invitation_usage_count'] = (int) ($settings['invitation_usage_count'] ?? 0) + 1;
        update_option(self::OPTION_KEY, $settings, false);
    }

    public static function get_offer_types(): array {
        $saved = get_option(self::OFFER_TYPES_OPTION, null);
        if (!is_array($saved) || empty($saved)) {
            return self::get_default_offer_types();
        }
        $validated = array();
        foreach ($saved as $item) {
            if (isset($item['key'], $item['label']) && is_string($item['key']) && is_string($item['label']) && $item['key'] !== '') {
                $validated[] = array(
                    'key'              => sanitize_key($item['key']),
                    'label'            => sanitize_text_field($item['label']),
                    'verleih_moeglich' => !empty($item['verleih_moeglich']),
                );
            }
        }
        return !empty($validated) ? $validated : self::get_default_offer_types();
    }

    public static function get_default_offer_types(): array {
        return array(
            array('key' => 'verleih', 'label' => 'Zu verleihen', 'verleih_moeglich' => true),
            array('key' => 'verkauf', 'label' => 'Zu verkaufen', 'verleih_moeglich' => false),
        );
    }

    public static function get_offer_type_keys(): array {
        return array_column(self::get_offer_types(), 'key');
    }

    public static function is_verleih_type(string $key): bool {
        foreach (self::get_offer_types() as $type) {
            if ($type['key'] === $key) {
                return !empty($type['verleih_moeglich']);
            }
        }
        return false;
    }

    public function handle_add_offer_type(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }
        check_admin_referer('pinnwand_add_offer_type', 'pinnwand_offer_type_nonce');

        $label = isset($_POST['offer_type_label']) ? sanitize_text_field(wp_unslash($_POST['offer_type_label'])) : '';
        $key   = isset($_POST['offer_type_key'])   ? sanitize_key(wp_unslash($_POST['offer_type_key'])) : '';

        $redirect = admin_url('admin.php?page=pinnwand-offer-types');

        if ($label === '') {
            wp_safe_redirect(add_query_arg('pw_ot_error', 'empty_label', $redirect));
            exit;
        }

        if ($key === '') {
            $key = sanitize_title($label);
            $key = (string) preg_replace('/[^a-z0-9_-]/', '', $key);
        }

        if ($key === '') {
            wp_safe_redirect(add_query_arg('pw_ot_error', 'empty_key', $redirect));
            exit;
        }

        $types = self::get_offer_types();
        foreach ($types as $type) {
            if ($type['key'] === $key) {
                wp_safe_redirect(add_query_arg('pw_ot_error', 'duplicate_key', $redirect));
                exit;
            }
        }

        $verleih_moeglich = !empty($_POST['verleih_moeglich']);
        $types[] = array('key' => $key, 'label' => $label, 'verleih_moeglich' => $verleih_moeglich);
        update_option(self::OFFER_TYPES_OPTION, $types, false);

        wp_safe_redirect(add_query_arg('pw_ot_action', 'added', $redirect));
        exit;
    }

    public function handle_update_offer_type(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }
        check_admin_referer('pinnwand_update_offer_type', 'pinnwand_offer_type_nonce');

        $old_key  = isset($_POST['offer_type_key'])     ? sanitize_key(wp_unslash($_POST['offer_type_key'])) : '';
        $label    = isset($_POST['offer_type_label'])   ? sanitize_text_field(wp_unslash($_POST['offer_type_label'])) : '';
        $redirect = admin_url('admin.php?page=pinnwand-offer-types');

        if ($old_key === '' || $label === '') {
            wp_safe_redirect(add_query_arg('pw_ot_error', 'empty_label', $redirect));
            exit;
        }

        $new_key = $old_key;
        if (isset($_POST['offer_type_new_key'])) {
            $new_key = sanitize_key(wp_unslash($_POST['offer_type_new_key']));
            if ($new_key === '') {
                wp_safe_redirect(add_query_arg('pw_ot_error', 'empty_new_key', $redirect));
                exit;
            }

            if ($new_key !== $old_key) {
                $article_ids = get_posts(array(
                    'post_type'      => 'pw_artikel',
                    'post_status'    => array('publish', 'draft'),
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => array(array('key' => 'pw_offer_type', 'value' => $old_key, 'compare' => '=')),
                ));
                if (!empty($article_ids)) {
                    $new_key = $old_key;
                } else {
                    $types = self::get_offer_types();
                    foreach ($types as $type) {
                        if ($type['key'] === $new_key) {
                            wp_safe_redirect(add_query_arg('pw_ot_error', 'duplicate_key_update', $redirect));
                            exit;
                        }
                    }
                }
            }
        }

        $types   = self::get_offer_types();
        $updated = false;
        foreach ($types as &$type) {
            if ($type['key'] === $old_key) {
                $type['key']              = $new_key;
                $type['label']            = $label;
                $type['verleih_moeglich'] = !empty($_POST['verleih_moeglich']);
                $updated = true;
                break;
            }
        }
        unset($type);

        if ($updated) {
            update_option(self::OFFER_TYPES_OPTION, $types, false);
        }

        wp_safe_redirect(add_query_arg('pw_ot_action', 'updated', $redirect));
        exit;
    }

    public function handle_delete_offer_type(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }
        check_admin_referer('pinnwand_delete_offer_type', 'pinnwand_offer_type_nonce');

        $key      = isset($_POST['offer_type_key']) ? sanitize_key(wp_unslash($_POST['offer_type_key'])) : '';
        $redirect = admin_url('admin.php?page=pinnwand-offer-types');

        if ($key === '') {
            wp_safe_redirect($redirect);
            exit;
        }

        $article_ids = get_posts(array(
            'post_type'       => 'pw_artikel',
            'post_status'     => 'publish',
            'posts_per_page'  => 1,
            'fields'          => 'ids',
            'meta_query'      => array(array('key' => 'pw_offer_type', 'value' => $key, 'compare' => '=')),
        ));

        if (!empty($article_ids)) {
            wp_safe_redirect(add_query_arg(array('pw_ot_error' => 'has_articles', 'pw_ot_key' => $key), $redirect));
            exit;
        }

        $types = array_values(array_filter(self::get_offer_types(), static function ($type) use ($key) {
            return $type['key'] !== $key;
        }));
        update_option(self::OFFER_TYPES_OPTION, $types, false);

        wp_safe_redirect(add_query_arg('pw_ot_action', 'deleted', $redirect));
        exit;
    }

    public function render_offer_types_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }

        $offer_types = self::get_offer_types();
        $ot_action   = isset($_GET['pw_ot_action']) ? sanitize_key(wp_unslash($_GET['pw_ot_action'])) : '';
        $ot_error    = isset($_GET['pw_ot_error'])  ? sanitize_key(wp_unslash($_GET['pw_ot_error'])) : '';
        $ot_key      = isset($_GET['pw_ot_key'])    ? sanitize_key(wp_unslash($_GET['pw_ot_key'])) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Inseratetypen', 'pinnwand'); ?></h1>

            <?php if ($ot_action === 'added') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Inseratetyp hinzugefuegt.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_action === 'updated') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Bezeichnung aktualisiert.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_action === 'deleted') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Inseratetyp geloescht.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_error === 'duplicate_key') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Dieser Key existiert bereits.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_error === 'duplicate_key_update') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Dieser Key existiert bereits — bitte einen anderen Key waehlen.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_error === 'empty_label') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Bitte eine Bezeichnung eingeben.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_error === 'empty_new_key') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Key darf nicht leer sein.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_error === 'empty_key') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Aus der Bezeichnung konnte kein gueltiger Key generiert werden. Bitte Key manuell eingeben.', 'pinnwand'); ?></p></div>
            <?php elseif ($ot_error === 'has_articles') : ?>
                <div class="notice notice-error is-dismissible"><p>
                    <?php
                    $blocked_count = count(get_posts(array(
                        'post_type'      => 'pw_artikel',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'meta_query'     => array(array('key' => 'pw_offer_type', 'value' => $ot_key, 'compare' => '=')),
                    )));
                    echo esc_html(
                        sprintf(
                            /* translators: %d number of articles */
                            __('Dieser Inseratetyp kann nicht geloescht werden — es gibt noch %d Inserate mit diesem Typ.', 'pinnwand'),
                            $blocked_count
                        )
                    );
                    ?>
                </p></div>
            <?php endif; ?>

            <?php
            $page_url  = admin_url('admin.php?page=pinnwand-offer-types');
            $edit_key  = isset($_GET['pw_edit']) ? sanitize_key(wp_unslash($_GET['pw_edit'])) : '';
            $edit_type = null;
            foreach ($offer_types as $ot) {
                if ($ot['key'] === $edit_key) {
                    $edit_type = $ot;
                    break;
                }
            }
            ?>

            <?php if ($edit_type !== null) : ?>

            <h2><?php esc_html_e('Typ bearbeiten', 'pinnwand'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:500px;">
                <input type="hidden" name="action" value="pinnwand_update_offer_type" />
                <input type="hidden" name="offer_type_key" value="<?php echo esc_attr($edit_type['key']); ?>" />
                <?php wp_nonce_field('pinnwand_update_offer_type', 'pinnwand_offer_type_nonce'); ?>
                <table class="form-table" role="presentation">
                    <?php
                    $edit_count = count(get_posts(array(
                        'post_type' => 'pw_artikel', 'post_status' => array('publish', 'draft'),
                        'posts_per_page' => 1, 'fields' => 'ids',
                        'meta_query' => array(array('key' => 'pw_offer_type', 'value' => $edit_type['key'], 'compare' => '=')),
                    )));
                    ?>
                    <tr>
                        <th scope="row"><label for="offer_type_new_key"><?php esc_html_e('Key', 'pinnwand'); ?><?php if ($edit_count === 0) : ?> <span aria-hidden="true" style="color:red;">*</span><?php endif; ?></label></th>
                        <td>
                            <?php if ($edit_count === 0) : ?>
                                <input type="text" id="offer_type_new_key" name="offer_type_new_key" value="<?php echo esc_attr($edit_type['key']); ?>" class="regular-text" pattern="[a-z0-9_-]+" required />
                                <p class="description"><?php esc_html_e('Nur a-z, 0-9, - und _', 'pinnwand'); ?></p>
                            <?php else : ?>
                                <code><?php echo esc_html($edit_type['key']); ?></code>
                                <p class="description"><?php esc_html_e('Key kann nicht geaendert werden solange Inserate diesem Typ zugeordnet sind.', 'pinnwand'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="offer_type_label_edit"><?php esc_html_e('Bezeichnung', 'pinnwand'); ?> <span aria-hidden="true" style="color:red;">*</span></label></th>
                        <td><input type="text" id="offer_type_label_edit" name="offer_type_label" value="<?php echo esc_attr($edit_type['label']); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Verleih moeglich', 'pinnwand'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="verleih_moeglich" value="1" <?php checked(!empty($edit_type['verleih_moeglich'])); ?> />
                                <?php esc_html_e('Ausleih-Status aktivieren fuer diesen Typ', 'pinnwand'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Speichern', 'pinnwand'), 'primary', 'submit', false); ?>
                <a href="<?php echo esc_url($page_url); ?>" class="button" style="margin-left:4px;"><?php esc_html_e('Abbrechen', 'pinnwand'); ?></a>
            </form>

            <?php else : ?>

            <h2><?php esc_html_e('Neuen Typ hinzufuegen', 'pinnwand'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:500px;">
                <input type="hidden" name="action" value="pinnwand_add_offer_type" />
                <?php wp_nonce_field('pinnwand_add_offer_type', 'pinnwand_offer_type_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="offer_type_key"><?php esc_html_e('Key', 'pinnwand'); ?> <span aria-hidden="true" style="color:red;">*</span></label></th>
                        <td>
                            <input type="text" id="offer_type_key" name="offer_type_key" value="" class="regular-text" pattern="[a-z0-9_-]+" required />
                            <p class="description"><?php esc_html_e('Eindeutiger Bezeichner (nur a-z, 0-9, - und _).', 'pinnwand'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="offer_type_label"><?php esc_html_e('Bezeichnung', 'pinnwand'); ?> <span aria-hidden="true" style="color:red;">*</span></label></th>
                        <td>
                            <input type="text" id="offer_type_label" name="offer_type_label" value="" class="regular-text" required />
                            <p class="description"><?php esc_html_e('Angezeigter Name, z.B. "Zu verschenken"', 'pinnwand'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Verleih moeglich', 'pinnwand'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="verleih_moeglich" value="1" />
                                <?php esc_html_e('Ausleih-Status aktivieren fuer diesen Typ', 'pinnwand'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Typ hinzufuegen', 'pinnwand'), 'primary', 'submit', false); ?>
            </form>

            <?php endif; ?>

            <h2><?php esc_html_e('Aktuelle Typen', 'pinnwand'); ?></h2>
            <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Typen mit vorhandenen Inseraten koennen nicht geloescht werden.', 'pinnwand'); ?></p>
            <table class="widefat striped" style="margin-bottom:28px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Key', 'pinnwand'); ?></th>
                        <th><?php esc_html_e('Bezeichnung', 'pinnwand'); ?></th>
                        <th><?php esc_html_e('Verleih moeglich', 'pinnwand'); ?></th>
                        <th><?php esc_html_e('Inserate', 'pinnwand'); ?></th>
                        <th><?php esc_html_e('Aktionen', 'pinnwand'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offer_types as $type) :
                        $type_count = count(get_posts(array(
                            'post_type'      => 'pw_artikel',
                            'post_status'    => array('publish', 'draft'),
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'meta_query'     => array(array('key' => 'pw_offer_type', 'value' => $type['key'], 'compare' => '=')),
                        )));
                    ?>
                        <tr>
                            <td><code><?php echo esc_html($type['key']); ?></code></td>
                            <td><?php echo esc_html($type['label']); ?></td>
                            <td><?php echo !empty($type['verleih_moeglich']) ? esc_html__('Ja', 'pinnwand') : '—'; ?></td>
                            <td><?php echo esc_html((string) $type_count); ?></td>
                            <td style="white-space:nowrap;">
                                <span style="display:inline-flex;gap:6px;align-items:center;">
                                    <a href="<?php echo esc_url(add_query_arg('pw_edit', $type['key'], $page_url)); ?>" class="button-link" title="<?php esc_attr_e('Bearbeiten', 'pinnwand'); ?>" style="color:#50575e;cursor:pointer;line-height:1;vertical-align:middle;text-decoration:none;">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <?php if ($type_count === 0) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Inseratetyp wirklich loeschen?', 'pinnwand')); ?>');" style="display:contents;">
                                            <input type="hidden" name="action" value="pinnwand_delete_offer_type" />
                                            <input type="hidden" name="offer_type_key" value="<?php echo esc_attr($type['key']); ?>" />
                                            <?php wp_nonce_field('pinnwand_delete_offer_type', 'pinnwand_offer_type_nonce'); ?>
                                            <button type="submit" class="button-link" title="<?php esc_attr_e('Loeschen', 'pinnwand'); ?>" style="color:#50575e;cursor:pointer;line-height:1;vertical-align:middle;">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </form>
                                    <?php else : ?>
                                        <span style="color:#50575e;opacity:.3;line-height:1;vertical-align:middle;"><span class="dashicons dashicons-trash"></span></span>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr style="margin:32px 0;" />

            <section>
                <h2><?php esc_html_e('Shortcode-Referenz', 'pinnwand'); ?></h2>
                <p class="description"><?php esc_html_e('Die folgenden Shortcodes koennen auf beliebigen WordPress-Seiten eingesetzt werden.', 'pinnwand'); ?></p>

                <table class="widefat pw-shortcode-table" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:38%"><?php esc_html_e('Shortcode', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Beschreibung', 'pinnwand'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[pw_search_form]</code></td>
                            <td><?php esc_html_e('Zeigt die Suchmaske mit allen Inseraten (alle Typen). Besucher koennen nach Inseratetyp filtern.', 'pinnwand'); ?></td>
                        </tr>
                        <?php foreach ($offer_types as $type) : ?>
                            <tr>
                                <td><code>[pw_search_form offer_type="<?php echo esc_attr($type['key']); ?>"]</code></td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s offer type label */
                                            __('Zeigt nur "%s"-Inserate. Der Inseratetyp-Filter wird ausgeblendet.', 'pinnwand'),
                                            $type['label']
                                        )
                                    );
                                    if ($type['key'] === 'verleih') {
                                        echo ' ' . esc_html__('Die Checkbox "Ausgeliehene anzeigen" bleibt verfuegbar.', 'pinnwand');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><code>[pw_article_form]</code></td>
                            <td><?php esc_html_e('Formular zum Erfassen und Bearbeiten von Artikeln. Nur fuer eingeloggte Benutzer mit entsprechender Berechtigung sichtbar.', 'pinnwand'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[pw_user_dashboard]</code></td>
                            <td><?php esc_html_e('Uebersicht aller eigenen Inserate mit Bearbeitungs- und Loeschfunktion. Nur fuer eingeloggte Benutzer.', 'pinnwand'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[pw_profile_form]</code></td>
                            <td><?php esc_html_e('Profilformular fuer Name, Telefon, Adresse. Enthaelt auch Datenexport und Profil-Loeschung. Nur fuer eingeloggte Benutzer.', 'pinnwand'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
        <?php
    }

    private function sanitize_date(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date) {
            return '';
        }

        return $date->format('Y-m-d');
    }
}
