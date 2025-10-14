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

    private const AGREEMENT_CAPABILITIES = [
        'read_estate_agreement'               => true,
        'read_private_estate_agreements'      => true,
        'edit_estate_agreement'               => true,
        'edit_estate_agreements'              => true,
        'edit_others_estate_agreements'       => true,
        'publish_estate_agreements'           => true,
        'delete_estate_agreement'             => false,
        'delete_estate_agreements'            => false,
        'delete_others_estate_agreements'     => false,
        'delete_private_estate_agreements'    => false,
        'delete_published_estate_agreements'  => false,
    ];

    private const SEARCH_CAPABILITIES = [
        'read_estate_search'                 => true,
        'read_private_estate_searches'       => true,
        'edit_estate_search'                 => true,
        'edit_estate_searches'               => true,
        'edit_others_estate_searches'        => true,
        'publish_estate_searches'            => true,
        'delete_estate_search'               => false,
        'delete_estate_searches'             => false,
        'delete_others_estate_searches'      => false,
        'delete_private_estate_searches'     => false,
        'delete_published_estate_searches'   => false,
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

        $capabilities = array_merge(
            self::BASE_CAPABILITIES,
            self::PROPERTY_CAPABILITIES,
            self::AGREEMENT_CAPABILITIES,
            self::SEARCH_CAPABILITIES
        );

        if (!$role instanceof \WP_Role) {
            $role = add_role(
                self::AGENT_ROLE,
                __('Agent nieruchomości', 'estate-office'),
                $capabilities
            );
        }

        if ($role instanceof \WP_Role) {
            foreach ($capabilities as $capability => $granted) {
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
            'read_estate_agreement',
            'read_private_estate_agreements',
            'edit_estate_agreement',
            'edit_estate_agreements',
            'edit_others_estate_agreements',
            'publish_estate_agreements',
            'delete_estate_agreement',
            'delete_estate_agreements',
            'delete_others_estate_agreements',
            'delete_private_estate_agreements',
            'delete_published_estate_agreements',
            'read_estate_search',
            'read_private_estate_searches',
            'edit_estate_search',
            'edit_estate_searches',
            'edit_others_estate_searches',
            'publish_estate_searches',
            'delete_estate_search',
            'delete_estate_searches',
            'delete_others_estate_searches',
            'delete_private_estate_searches',
            'delete_published_estate_searches',
        ];

        foreach ($caps as $capability) {
            $administrator->add_cap($capability);
        }
    }
}
