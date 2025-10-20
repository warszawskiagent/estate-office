<?php

declare(strict_types=1);

namespace EstateOffice\PostTypes;

use EstateOffice\Roles\Manager as RolesManager;
use EstateOffice\PostTypes\AgreementRegister;
use WP_Query;
use WP_Screen;
use WP_User;
use WP_User_Query;

use function absint;
use function add_action;
use function array_filter;
use function array_values;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_current_screen;
use function is_admin;
use function is_array;
use function selected;
use function wp_unslash;


defined('ABSPATH') || exit;

final class ManagerFilters
{
    private const PARAM = 'estate_manager';

    public static function bootstrap(): void
    {
        add_action('restrict_manage_posts', [self::class, 'renderFilter']);
        add_action('pre_get_posts', [self::class, 'applyFilter']);
    }

    public static function renderFilter(string $postType): void
    {
        if (!in_array($postType, self::getSupportedPostTypes(), true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || $screen->id !== 'edit-' . $postType) {
            return;
        }

        if (!self::canCurrentUserManage($postType)) {
            return;
        }

        $agents = self::getAgents();
        if (!$agents) {
            return;
        }

        $selected = isset($_GET[self::PARAM]) ? absint(wp_unslash((string) $_GET[self::PARAM])) : 0;

        echo '<label class="screen-reader-text" for="estate-office-manager-filter">' . esc_html__('Filtruj według opiekuna', 'estate-office') . '</label>';
        echo '<select name="' . esc_attr(self::PARAM) . '" id="estate-office-manager-filter" class="postform">';
        echo '<option value="">' . esc_html__('Wszyscy opiekunowie', 'estate-office') . '</option>';

        foreach ($agents as $agent) {
            echo '<option value="' . esc_attr((string) $agent->ID) . '"' . selected($selected, $agent->ID, false) . '>' . esc_html($agent->display_name ?: $agent->user_login) . '</option>';
        }

        echo '</select>';
    }

    public static function applyFilter(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');
        if (!is_string($postType) || !in_array($postType, self::getSupportedPostTypes(), true)) {
            return;
        }

        if (!self::canCurrentUserManage($postType)) {
            return;
        }

        $manager = isset($_GET[self::PARAM]) ? absint(wp_unslash((string) $_GET[self::PARAM])) : 0;
        if ($manager <= 0) {
            return;
        }

        $metaKey = self::getMetaKey($postType);
        if ($metaKey === null) {
            return;
        }

        $metaQuery = $query->get('meta_query');
        if (!is_array($metaQuery)) {
            $metaQuery = [];
        }

        $metaQuery[] = [
            'key'   => $metaKey,
            'value' => $manager,
        ];

        $query->set('meta_query', $metaQuery);
    }

    /**
     * @return array<int, WP_User>
     */
    private static function getAgents(): array
    {
        $query = new WP_User_Query([
            'role__in' => [RolesManager::AGENT_ROLE, 'administrator'],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'number'   => 200,
        ]);

        return array_values(array_filter(
            $query->get_results(),
            static fn($user) => $user instanceof WP_User
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function getSupportedPostTypes(): array
    {
        return [
            PropertyRegister::POST_TYPE,
            SearchRegister::POST_TYPE,
            ClientRegister::POST_TYPE,
            AgreementRegister::POST_TYPE,
        ];
    }

    private static function getMetaKey(string $postType): ?string
    {
        return match ($postType) {
            PropertyRegister::POST_TYPE => 'estate_property_manager',
            SearchRegister::POST_TYPE   => 'estate_search_manager',
            ClientRegister::POST_TYPE   => 'estate_client_manager',
            AgreementRegister::POST_TYPE => 'estate_agreement_manager',
            default                     => null,
        };
    }

    private static function canCurrentUserManage(string $postType): bool
    {
        return match ($postType) {
            PropertyRegister::POST_TYPE => current_user_can('edit_estate_properties'),
            SearchRegister::POST_TYPE   => current_user_can('edit_estate_searches'),
            ClientRegister::POST_TYPE   => current_user_can('edit_estate_clients'),
            AgreementRegister::POST_TYPE => current_user_can('edit_estate_agreements'),
            default                     => false,
        };
    }
}
