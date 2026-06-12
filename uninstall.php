<?php

if (!defined('WP_UNINSTALL_PLUGIN') || !defined('ABSPATH')) {
    exit;
}

if (!defined('PINNWAND_DELETE_ALL_DATA') || PINNWAND_DELETE_ALL_DATA !== true) {
    return;
}

$posts = get_posts(
    array(
        'post_type' => 'pw_artikel',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    )
);

foreach ($posts as $post_id) {
    wp_delete_post((int) $post_id, true);
}

$taxonomies = array('pw_kategorie', 'pw_tag');
foreach ($taxonomies as $taxonomy) {
    $terms = get_terms(
        array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'ids',
        )
    );

    if (is_wp_error($terms)) {
        continue;
    }

    foreach ($terms as $term_id) {
        wp_delete_term((int) $term_id, $taxonomy);
    }
}

remove_role('pinnwand_nutzer');

$users = get_users(
    array(
        'meta_key' => 'pw_phone',
        'fields' => 'ids',
    )
);

foreach ($users as $user_id) {
    delete_user_meta((int) $user_id, 'pw_phone');
    delete_user_meta((int) $user_id, 'pw_address');
    delete_user_meta((int) $user_id, 'pw_zip');
    delete_user_meta((int) $user_id, 'pw_city');
}

// Rate-Limiter-Locks entfernen
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('pinnwand_create_lock_') . '%'
    )
);

delete_option('pinnwand_version');
delete_option('pinnwand_db_version');
delete_option('pinnwand_settings');
delete_option('pinnwand_error_log');
