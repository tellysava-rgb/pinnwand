<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Logger {
    private const OPTION_KEY = 'pinnwand_error_log';
    private const MAX_ENTRIES = 200;

    public function register_admin_menu(): void {
        add_submenu_page(
            'pinnwand-settings',
            __('Fehlerlog', 'pinnwand'),
            __('Fehlerlog', 'pinnwand'),
            'manage_options',
            'pinnwand-error-log',
            array($this, 'render_log_page')
        );
    }

    public static function error(string $message, array $context = array()): void {
        $entry = array(
            'time' => current_time('mysql'),
            'message' => sanitize_text_field($message),
            'context' => self::sanitize_context($context),
        );

        $logs = get_option(self::OPTION_KEY, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        array_unshift($logs, $entry);
        if (count($logs) > self::MAX_ENTRIES) {
            $logs = array_slice($logs, 0, self::MAX_ENTRIES);
        }

        update_option(self::OPTION_KEY, $logs, false);
    }

    public function render_log_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'pinnwand'));
        }

        if (isset($_POST['pinnwand_clear_log']) && check_admin_referer('pinnwand_clear_log', 'pinnwand_clear_log_nonce')) {
            update_option(self::OPTION_KEY, array(), false);
            echo '<div class="notice notice-success"><p>' . esc_html__('Log geleert.', 'pinnwand') . '</p></div>';
        }

        $logs = get_option(self::OPTION_KEY, array());
        if (!is_array($logs)) {
            $logs = array();
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pinnwand Fehlerlog', 'pinnwand'); ?></h1>
            <form method="post" style="margin-bottom:12px;">
                <?php wp_nonce_field('pinnwand_clear_log', 'pinnwand_clear_log_nonce'); ?>
                <input type="hidden" name="pinnwand_clear_log" value="1" />
                <?php submit_button(__('Log leeren', 'pinnwand'), 'secondary', 'submit', false); ?>
            </form>

            <?php if (empty($logs)) : ?>
                <p><?php esc_html_e('Keine Fehlereintraege vorhanden.', 'pinnwand'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Zeit', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Meldung', 'pinnwand'); ?></th>
                            <th><?php esc_html_e('Kontext', 'pinnwand'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($log['time'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($log['message'] ?? '')); ?></td>
                                <td><code><?php echo esc_html(wp_json_encode($log['context'] ?? array())); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function sanitize_context(array $context): array {
        $blocked_keys = array('password', 'pass', 'pwd', 'phone', 'address', 'zip', 'city', 'email');
        $clean = array();

        foreach ($context as $key => $value) {
            $safe_key = sanitize_key((string) $key);
            if (in_array($safe_key, $blocked_keys, true)) {
                $clean[$safe_key] = '[redacted]';
                continue;
            }

            if (is_scalar($value)) {
                $clean[$safe_key] = sanitize_text_field((string) $value);
                continue;
            }

            $clean[$safe_key] = '[complex]';
        }

        return $clean;
    }
}
