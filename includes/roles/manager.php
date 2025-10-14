<?php

declare(strict_types=1);

namespace EstateOffice\Roles;

defined('ABSPATH') || exit;

final class Manager
{
    public const AGENT_ROLE = 'estate_agent';

    private const CAPABILITIES = [
        'read'                   => true,
        'upload_files'           => true,
        'edit_posts'             => false,
        'delete_posts'           => false,
        'publish_posts'          => false,
        'edit_pages'             => false,
        'delete_pages'           => false,
        'manage_categories'      => false,
        'list_users'             => false,
        'delete_users'           => false,
        'promote_users'          => false,
        'remove_users'           => false,
        'create_users'           => false,
        'edit_users'             => false,
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
        if (!get_role(self::AGENT_ROLE)) {
            add_role(self::AGENT_ROLE, __('Agent nieruchomości', 'estate-office'), self::CAPABILITIES);
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
    }
}
