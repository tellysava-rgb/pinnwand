<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_Post_Types {
    public function register_post_types(): void {
        $labels = array(
            'name' => __('Artikel', 'pinnwand'),
            'singular_name' => __('Artikel', 'pinnwand'),
            'menu_name' => __('Pinnwand', 'pinnwand'),
            'add_new_item' => __('Neuen Artikel hinzufuegen', 'pinnwand'),
            'edit_item' => __('Artikel bearbeiten', 'pinnwand'),
            'view_item' => __('Artikel ansehen', 'pinnwand'),
            'all_items' => __('Alle Artikel', 'pinnwand'),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'pinnwand-all-articles',
            'has_archive' => true,
            'rewrite' => array('slug' => 'artikel'),
            'supports' => array('title', 'editor', 'author', 'thumbnail'),
            'show_in_rest' => true,
            'capability_type' => array('pw_artikel', 'pw_artikels'),
            'capabilities' => array(
                'edit_post' => 'edit_pw_artikel',
                'read_post' => 'read_pw_artikel',
                'delete_post' => 'delete_pw_artikel',
                'edit_posts' => 'edit_pw_artikels',
                'edit_others_posts' => 'edit_others_pw_artikels',
                'publish_posts' => 'publish_pw_artikels',
                'read_private_posts' => 'read_private_pw_artikels',
                'delete_posts' => 'delete_pw_artikels',
                'delete_private_posts' => 'delete_private_pw_artikels',
                'delete_published_posts' => 'delete_published_pw_artikels',
                'delete_others_posts' => 'delete_others_pw_artikels',
                'edit_private_posts' => 'edit_private_pw_artikels',
                'edit_published_posts' => 'edit_published_pw_artikels',
                'create_posts' => 'edit_pw_artikels',
            ),
            'map_meta_cap' => true,
        );

        register_post_type('pw_artikel', $args);
    }

    public function register_taxonomies(): void {
        register_taxonomy(
            'pw_kategorie',
            array('pw_artikel'),
            array(
                'label' => __('Kategorien', 'pinnwand'),
                'hierarchical' => true,
                'show_ui' => true,
                'show_in_rest' => true,
            )
        );

        register_taxonomy(
            'pw_tag',
            array('pw_artikel'),
            array(
                'labels' => array(
                    'name' => __('Keywords', 'pinnwand'),
                    'singular_name' => __('Keyword', 'pinnwand'),
                    'search_items' => __('Keywords durchsuchen', 'pinnwand'),
                    'popular_items' => __('Beliebte Keywords', 'pinnwand'),
                    'all_items' => __('Alle Keywords', 'pinnwand'),
                    'edit_item' => __('Keyword bearbeiten', 'pinnwand'),
                    'update_item' => __('Keyword aktualisieren', 'pinnwand'),
                    'add_new_item' => __('Neues Keyword hinzufuegen', 'pinnwand'),
                    'new_item_name' => __('Name des neuen Keywords', 'pinnwand'),
                    'separate_items_with_commas' => __('Keywords mit Komma trennen', 'pinnwand'),
                    'add_or_remove_items' => __('Keywords hinzufuegen oder entfernen', 'pinnwand'),
                    'choose_from_most_used' => __('Aus den haeufigsten Keywords waehlen', 'pinnwand'),
                    'not_found' => __('Keine Keywords gefunden.', 'pinnwand'),
                    'menu_name' => __('Keywords', 'pinnwand'),
                ),
                'label' => __('Keywords', 'pinnwand'),
                'hierarchical' => false,
                'show_ui' => true,
                'show_in_rest' => true,
            )
        );

        // Ensure at least one selectable category exists for frontend article creation.
        $existing_categories = get_terms(
            array(
                'taxonomy' => 'pw_kategorie',
                'hide_empty' => false,
                'fields' => 'ids',
                'number' => 1,
            )
        );
        if (!is_wp_error($existing_categories) && empty($existing_categories)) {
            wp_insert_term(__('Allerlei', 'pinnwand'), 'pw_kategorie');
        }
    }
}
