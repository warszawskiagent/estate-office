<?php

declare(strict_types=1);

namespace EstateOffice\Roles;

defined('ABSPATH') || exit;

final class Manager
{
    public const AGENT_ROLE = 'estate_agent';

    private const BASE_CAPABILITIES = [
        'read'              => true,
        'upload_files'      => true,
        'list_users'        => false,
        'delete_users'      => false,
        'promote_users'     => false,
        'remove_users'      => false,
        'create_users'      => false,
        'edit_users'        => false,
        'edit_posts'        => false,
        'delete_posts'      => false,
        'publish_posts'     => false,
        'edit_pages'        => false,
        'delete_pages'      => false,
        'manage_categories' => false,
    ];

    private const PROPERTY_CAPABILITIES = [
        'read_estate_property'              => true,
        'read_private_estate_properties'    => true,
        'edit_estate_property'              => true,
        'edit_estate_properties'            => true,
        'edit_others_estate_properties'     => true,
        'publish_estate_properties'         => true,
        'delete_estate_property'            => false,
        'delete_estate_properties'          => false,
        'delete_others_estate_properties'   => false,
        'delete_private_estate_properties'  => false,
        'delete_published_estate_properties'=> false,
    ];

    public static function activate(): void
    {
        self::register();
        self::addCapabilities();
    }

    public static function deactivate(): void
    {
        // Keep the role to avoid breaking assignments, but remove custom caps from administrators if needed in the future.
    }

    public static function register(): void
    {
        $role = get_role(self::AGENT_ROLE);

        if (!$role instanceof \WP_Role) {
            $role = add_role(
                self::AGENT_ROLE,
                __('Agent nieruchomości', 'estate-office'),
                array_merge(self::BASE_CAPABILITIES, self::PROPERTY_CAPABILITIES)
            );
        }

        if ($role instanceof \WP_Role) {
            foreach (self::BASE_CAPABILITIES + self::PROPERTY_CAPABILITIES as $capability => $granted) {
                if ($granted) {
                    $role->add_cap($capability);
                } else {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    private static function addCapabilities(): void
    {
        $administrator = get_role('administrator');
        if (!$administrator instanceof \WP_Role) {
            return;
        }

        $administrator->add_cap('manage_estate_office');
        $administrator->add_cap('edit_estate_office_records');
        $administrator->add_cap('delete_estate_office_records');

        $caps = [
            'read_estate_property',
            'read_private_estate_properties',
            'edit_estate_property',
            'edit_estate_properties',
            'edit_others_estate_properties',
            'publish_estate_properties',
            'delete_estate_property',
            'delete_estate_properties',
            'delete_others_estate_properties',
            'delete_private_estate_properties',
            'delete_published_estate_properties',
        ];

        foreach ($caps as $capability) {
            $administrator->add_cap($capability);
        }
    }
}
