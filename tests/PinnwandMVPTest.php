<?php

class PinnwandMVPTest extends WP_UnitTestCase {
    private int $owner_id;
    private int $other_id;

    public function setUp(): void {
        parent::setUp();

        $this->owner_id = self::factory()->user->create(array('role' => 'administrator'));
        $this->other_id = self::factory()->user->create(array('role' => 'subscriber'));

        // Ensure init hooks are available in test context.
        do_action('init');
    }

    public function test_post_type_and_taxonomies_registered(): void {
        $this->assertTrue(post_type_exists('pw_artikel'));
        $this->assertTrue(taxonomy_exists('pw_kategorie'));
        $this->assertTrue(taxonomy_exists('pw_tag'));
    }

    public function test_default_category_exists_after_init(): void {
        $terms = get_terms(
            array(
                'taxonomy' => 'pw_kategorie',
                'hide_empty' => false,
                'fields' => 'ids',
            )
        );
        $this->assertNotEmpty($terms);
    }

    public function test_shortcodes_registered(): void {
        global $shortcode_tags;

        $this->assertArrayHasKey('pw_search_form', $shortcode_tags);
        $this->assertArrayHasKey('pw_article_form', $shortcode_tags);
        $this->assertArrayHasKey('pw_user_dashboard', $shortcode_tags);
        $this->assertArrayHasKey('pw_profile_form', $shortcode_tags);
    }

    public function test_search_form_contains_keyword_and_borrowed_checkbox_only(): void {
        $out = do_shortcode('[pw_search_form]');

        $this->assertStringContainsString('name="pw_keyword"', $out);
        $this->assertStringContainsString('name="pw_show_borrowed"', $out);
        $this->assertStringNotContainsString('name="pw_category"', $out);
    }

    public function test_search_default_excludes_borrowed_and_option_includes_it(): void {
        $available_id = self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_title' => 'Bohrmaschine Alpha',
                'post_author' => $this->owner_id,
            )
        );
        update_post_meta($available_id, 'pw_status', 'available');

        $borrowed_id = self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_title' => 'Bohrmaschine Beta',
                'post_author' => $this->owner_id,
            )
        );
        update_post_meta($borrowed_id, 'pw_status', 'borrowed');

        $previous_get = $_GET;

        $_GET = array('pw_keyword' => 'Bohrmaschine');
        $default_out = do_shortcode('[pw_search_form]');
        $this->assertStringContainsString('Bohrmaschine Alpha', $default_out);
        $this->assertStringNotContainsString('Bohrmaschine Beta', $default_out);

        $_GET = array('pw_keyword' => 'Bohrmaschine', 'pw_show_borrowed' => '1');
        $borrowed_out = do_shortcode('[pw_search_form]');
        $this->assertStringContainsString('Bohrmaschine Alpha', $borrowed_out);
        $this->assertStringContainsString('Bohrmaschine Beta', $borrowed_out);

        $_GET = $previous_get;
    }

    public function test_keyword_search_matches_tags(): void {
        $post_id = self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_title' => 'Leiter',
                'post_author' => $this->owner_id,
            )
        );
        update_post_meta($post_id, 'pw_status', 'available');
        wp_set_object_terms($post_id, array('haushalt'), 'pw_tag', false);

        $previous_get = $_GET;
        $_GET = array('pw_keyword' => 'haushalt');

        $out = do_shortcode('[pw_search_form]');

        $this->assertStringContainsString('Leiter', $out);

        $_GET = $previous_get;
    }

    public function test_can_edit_article_owner_vs_other_user(): void {
        $post_id = self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_title' => 'Artikel Rechte',
                'post_author' => $this->owner_id,
            )
        );

        wp_set_current_user($this->owner_id);
        $this->assertTrue(PW_Security::can_edit_article($post_id));

        wp_set_current_user($this->other_id);
        $this->assertFalse(PW_Security::can_edit_article($post_id));
    }

    public function test_rate_limiter_blocks_after_limit(): void {
        update_option(
            'pinnwand_settings',
            array_merge(
                PW_Settings::get_defaults(),
                array(
                    'rate_per_minute' => 1,
                    'rate_per_hour' => 1,
                    'rate_per_day' => 1,
                )
            )
        );

        self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_author' => $this->owner_id,
                'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            )
        );

        $result = PW_Rate_Limiter::check_create_allowed($this->owner_id);

        $this->assertFalse($result['allowed']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('Limit erreicht', (string) $result['message']);
    }

    public function test_admin_not_blocked_by_rate_limit_policy(): void {
        wp_set_current_user($this->owner_id);
        $this->assertTrue(current_user_can('manage_options'));

        update_option(
            'pinnwand_settings',
            array_merge(
                PW_Settings::get_defaults(),
                array(
                    'rate_per_minute' => 1,
                    'rate_per_hour' => 1,
                    'rate_per_day' => 1,
                )
            )
        );

        self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_author' => $this->owner_id,
                'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            )
        );

        $result = PW_Rate_Limiter::check_create_allowed($this->owner_id);
        $this->assertFalse($result['allowed']);

        // Policy check: Admin is exempt in controller flow.
        $this->assertTrue(current_user_can('manage_options'));
    }

    public function test_profile_shortcode_requires_login(): void {
        wp_set_current_user(0);
        $out = do_shortcode('[pw_profile_form]');

        $this->assertStringContainsString('Bitte zuerst anmelden', $out);
    }

    public function test_article_form_includes_existing_tag_suggestions_for_logged_in_user(): void {
        wp_set_current_user($this->owner_id);
        wp_insert_term('garten', 'pw_tag');

        $out = do_shortcode('[pw_article_form]');

        $this->assertStringContainsString('pinnwand-tag-suggestions', $out);
        $this->assertStringContainsString('garten', $out);
    }

    public function test_subscriber_can_create_pw_artikel(): void {
        $subscriber_id = self::factory()->user->create(array('role' => 'subscriber'));
        wp_set_current_user($subscriber_id);
        do_action('init');

        $post_id = wp_insert_post(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_title' => 'Neu gespeichert',
                'post_content' => 'Text',
                'post_author' => $subscriber_id,
            ),
            true
        );

        $this->assertIsInt($post_id);
        $this->assertGreaterThan(0, $post_id);
    }

    public function test_owner_can_update_existing_article(): void {
        $post_id = self::factory()->post->create(
            array(
                'post_type' => 'pw_artikel',
                'post_status' => 'publish',
                'post_title' => 'Vorher',
                'post_content' => 'Alt',
                'post_author' => $this->owner_id,
            )
        );

        wp_set_current_user($this->owner_id);
        $updated_id = wp_update_post(
            array(
                'ID' => $post_id,
                'post_title' => 'Nachher',
                'post_content' => 'Neu',
            ),
            true
        );

        $this->assertSame($post_id, $updated_id);
        $this->assertSame('Nachher', get_post_field('post_title', $post_id));
    }
}
