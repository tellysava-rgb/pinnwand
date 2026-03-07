<?php

if (!defined('ABSPATH')) {
    exit;
}

class PW_User_Roles {
    public function register_roles(): void {
        $article_caps = array(
            'edit_pw_artikels',
            'publish_pw_artikels',
            'delete_pw_artikels',
            'edit_published_pw_artikels',
            'delete_published_pw_artikels',
            'read_pw_artikel',
        );

        $role = get_role('pinnwand_nutzer');
        if (!$role) {
            $role = add_role(
                'pinnwand_nutzer',
                __('Pinnwand Nutzer', 'pinnwand'),
                array('read' => true)
            );
        }

        if ($role instanceof WP_Role) {
            $role->add_cap('read', true);
            $role->add_cap('upload_files', true);
            foreach ($article_caps as $cap) {
                $role->add_cap($cap, true);
            }
        }

        $admin_role = get_role('administrator');
        if ($admin_role instanceof WP_Role) {
            foreach ($article_caps as $cap) {
                $admin_role->add_cap($cap, true);
            }
            $admin_role->add_cap('edit_others_pw_artikels', true);
            $admin_role->add_cap('delete_others_pw_artikels', true);
            $admin_role->add_cap('read_private_pw_artikels', true);
            $admin_role->add_cap('edit_private_pw_artikels', true);
            $admin_role->add_cap('delete_private_pw_artikels', true);
        }

        // Keep common logged-in roles functional for creating/editing own board items.
        $common_roles = array('subscriber', 'contributor', 'author', 'editor');
        foreach ($common_roles as $common_role_name) {
            $common_role = get_role($common_role_name);
            if (!$common_role instanceof WP_Role) {
                continue;
            }

            $common_role->add_cap('upload_files', true);
            foreach ($article_caps as $cap) {
                $common_role->add_cap($cap, true);
            }
        }
    }
}
