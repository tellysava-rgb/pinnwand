<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Rate_Limiter {
    public static function check_create_allowed(int $user_id): array {
        $settings = PW_Settings::get();

        $windows = array(
            'minute' => array('seconds' => MINUTE_IN_SECONDS, 'limit' => (int) $settings['rate_per_minute']),
            'hour' => array('seconds' => HOUR_IN_SECONDS, 'limit' => (int) $settings['rate_per_hour']),
            'day' => array('seconds' => DAY_IN_SECONDS, 'limit' => (int) $settings['rate_per_day']),
        );

        $now = time();
        foreach ($windows as $label => $window) {
            $count = self::count_recent_posts($user_id, $window['seconds']);
            if ($count >= $window['limit']) {
                $retry_at = self::next_allowed_timestamp($user_id, $window['seconds']);

                return array(
                    'allowed' => false,
                    'window' => $label,
                    'limit' => $window['limit'],
                    'count' => $count,
                    'retry_at' => $retry_at,
                    'message' => self::build_limit_message($label, $window['limit'], $retry_at, $settings),
                );
            }
        }

        return array(
            'allowed' => true,
            'retry_at' => $now,
            'message' => '',
        );
    }

    public static function acquire_create_lock(int $user_id): bool {
        $key = 'pinnwand_create_lock_' . $user_id;
        $created = add_option($key, (string) time(), '', false);
        if ($created) {
            return true;
        }

        $existing = (int) get_option($key, 0);
        if ($existing > 0 && (time() - $existing) > 15) {
            update_option($key, (string) time(), false);
            return true;
        }

        return false;
    }

    public static function release_create_lock(int $user_id): void {
        delete_option('pinnwand_create_lock_' . $user_id);
    }

    private static function count_recent_posts(int $user_id, int $seconds): int {
        $query = new WP_Query(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'author' => $user_id,
                'fields' => 'ids',
                'posts_per_page' => 1,
                'date_query' => array(
                    array(
                        'after' => gmdate('Y-m-d H:i:s', time() - $seconds),
                        'inclusive' => true,
                    ),
                ),
            )
        );

        return (int) $query->found_posts;
    }

    private static function next_allowed_timestamp(int $user_id, int $seconds): int {
        $query = new WP_Query(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'author' => $user_id,
                'fields' => 'ids',
                'posts_per_page' => 100,
                'orderby' => 'date',
                'order' => 'DESC',
                'date_query' => array(
                    array(
                        'after' => gmdate('Y-m-d H:i:s', time() - $seconds),
                        'inclusive' => true,
                    ),
                ),
            )
        );

        $latest_ts = 0;
        foreach ($query->posts as $post_id) {
            $ts = (int) get_post_time('U', true, (int) $post_id);
            if ($ts > $latest_ts) {
                $latest_ts = $ts;
            }
        }

        if ($latest_ts === 0) {
            return time() + $seconds;
        }

        return $latest_ts + $seconds;
    }

    private static function build_limit_message(string $window, int $limit, int $retry_at, array $settings): string {
        $window_label = $window;
        if ($window === 'minute') {
            $window_label = __('Minute', 'pinnwand');
        } elseif ($window === 'hour') {
            $window_label = __('Stunde', 'pinnwand');
        } elseif ($window === 'day') {
            $window_label = __('Tag', 'pinnwand');
        }

        $retry = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $retry_at);

        return sprintf(
            /* translators: 1 limit; 2 window; 3 next time; 4 minute limit; 5 hour limit; 6 day limit */
            __('Limit erreicht: max. %1$d pro %2$s. Naechster Versuch ab %3$s. Aktive Limits: %4$d/Minute, %5$d/Stunde, %6$d/Tag.', 'pinnwand'),
            $limit,
            $window_label,
            $retry,
            (int) $settings['rate_per_minute'],
            (int) $settings['rate_per_hour'],
            (int) $settings['rate_per_day']
        );
    }
}
