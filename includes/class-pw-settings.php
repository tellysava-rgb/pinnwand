<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Settings {
    private const OPTION_KEY = 'pinnwand_settings';

    public function register_admin_menu(): void {
        add_menu_page(
            __('Pinnwand', 'pinnwand'),
            __('Pinnwand', 'pinnwand'),
            'manage_options',
            'pinnwand-settings',
            array($this, 'render_settings_page'),
            'dashicons-index-card',
            26
        );

        add_submenu_page(
            'pinnwand-settings',
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
