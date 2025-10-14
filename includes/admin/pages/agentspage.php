<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\AgentProfile;
use EstateOffice\PostTypes\ClientRegister;
use EstateOffice\PostTypes\PropertyRegister;
use EstateOffice\PostTypes\SearchRegister;
use EstateOffice\Roles\Manager as RolesManager;
use WP_Screen;
use WP_User;
use WP_User_Query;

use function absint;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_sum;
use function current_user_can;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_avatar;
use function get_current_screen;
use function get_edit_user_link;
use function get_user_meta;
use function number_format_i18n;
use function paginate_links;
use function plugins_url;
use function sanitize_text_field;
use function sanitize_email;
use function wp_enqueue_style;
use function wp_get_attachment_image_url;
use function wp_strip_all_tags;
use function wp_trim_words;
use function wp_unslash;

use const ARRAY_A;
use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;
use const FILTER_VALIDATE_URL;

defined('ABSPATH') || exit;

final class AgentsPage extends BasePage
{
    private const PER_PAGE = 20;

    protected function canView(): bool
    {
        return current_user_can('list_users');
    }

    protected function getTitle(): string
    {
        return __('Agenci', 'estate-office');
    }

    protected function renderContent(): void
    {
        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $page     = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $perPage  = (int) apply_filters('estate_office/agents/per_page', self::PER_PAGE);
        $queryArgs = [
            'role__in'     => [RolesManager::AGENT_ROLE, 'administrator'],
            'orderby'      => 'display_name',
            'order'        => 'ASC',
            'number'       => $perPage,
            'paged'        => $page,
            'count_total'  => true,
        ];

        if ($search !== '') {
            $queryArgs['search']         = '*' . $search . '*';
            $queryArgs['search_columns'] = ['user_login', 'user_nicename', 'display_name', 'user_email'];
        }

        $userQuery = new WP_User_Query($queryArgs);

        $agents = array_values(array_filter(
            $userQuery->get_results(),
            static fn($user) => $user instanceof WP_User
        ));

        $total     = (int) $userQuery->get_total();
        $totals    = $this->getAssignmentCounts($agents);
        $pagination = $this->getPagination($total, $page, $perPage, $search);

        if (current_user_can('create_users')) {
            $addAgentUrl = add_query_arg(
                ['role' => RolesManager::AGENT_ROLE],
                admin_url('user-new.php')
            );

            echo '<a href="' . esc_url($addAgentUrl) . '" class="page-title-action">' . esc_html__('Dodaj nowego agenta', 'estate-office') . '</a>';
        }

        echo '<p class="description">' . esc_html__('Przeglądaj profile agentów, podglądaj dane kontaktowe i szybko przechodź do przypisanych rekordów CRM.', 'estate-office') . '</p>';

        echo '<form method="get" class="estate-office-agents">';
        echo '<input type="hidden" name="page" value="estate-office-agents" />';

        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="estate-office-agent-search">' . esc_html__('Szukaj agentów', 'estate-office') . '</label>';
        echo '<input type="search" id="estate-office-agent-search" name="s" value="' . esc_attr($search) . '" />';
        echo '<input type="submit" class="button" value="' . esc_attr__('Szukaj agentów', 'estate-office') . '" />';
        if ($search !== '') {
            $resetUrl = admin_url('admin.php?page=estate-office-agents');
            echo ' <a class="button button-link" href="' . esc_url($resetUrl) . '">' . esc_html__('Wyczyść filtr', 'estate-office') . '</a>';
        }
        echo '</p>';

        if ($agents) {
            $this->renderTable($agents, $totals);
        } else {
            echo '<p>' . esc_html__('Brak agentów spełniających kryteria wyszukiwania.', 'estate-office') . '</p>';
        }

        if ($pagination !== '') {
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">' . $pagination . '</div>';
            echo '</div>';
        }

        echo '</form>';
    }

    public static function enqueueAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || $screen->id !== 'estate-office-crm_page_estate-office-agents') {
            return;
        }

        wp_enqueue_style(
            'estate-office-agents',
            plugins_url('assets/css/agents.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );
    }

    /**
     * @param array<int, WP_User> $agents
     * @param array<int, array{properties:int,searches:int,clients:int}> $totals
     */
    private function renderTable(array $agents, array $totals): void
    {
        echo '<table class="widefat fixed striped estate-office-agents__table">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Agent', 'estate-office') . '</th>';
        echo '<th scope="col">' . esc_html__('Kontakt', 'estate-office') . '</th>';
        echo '<th scope="col">' . esc_html__('Przypisane rekordy', 'estate-office') . '</th>';
        echo '<th scope="col">' . esc_html__('Akcje', 'estate-office') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($agents as $agent) {
            $this->renderRow($agent, $totals[$agent->ID] ?? ['properties' => 0, 'searches' => 0, 'clients' => 0]);
        }

        echo '</tbody></table>';
    }

    /**
     * @param array{properties:int,searches:int,clients:int} $counts
     */
    private function renderRow(WP_User $agent, array $counts): void
    {
        $avatarId  = (int) get_user_meta($agent->ID, AgentProfile::META_AVATAR, true);
        $avatarUrl = $avatarId > 0 ? wp_get_attachment_image_url($avatarId, 'thumbnail') : '';
        $bio       = (string) get_user_meta($agent->ID, AgentProfile::META_BIOGRAPHY, true);
        $bio       = $bio !== '' ? wp_trim_words(wp_strip_all_tags($bio), 24, '…') : '';

        $contactParts = $this->getContactDetails($agent);

        $labels = [
            'properties' => __('Nieruchomości', 'estate-office'),
            'searches'   => __('Poszukiwania', 'estate-office'),
            'clients'    => __('Klienci', 'estate-office'),
        ];

        $totalCount = array_sum($counts);

        echo '<tr>';
        echo '<td class="column-primary">';
        echo '<div class="estate-office-agents__agent">';
        echo '<span class="estate-office-agents__avatar">';
        if ($avatarUrl) {
            echo '<img src="' . esc_url($avatarUrl) . '" alt="" />';
        } else {
            echo get_avatar($agent->ID, 48);
        }
        echo '</span>';
        echo '<div class="estate-office-agents__agent-info">';
        echo '<strong>' . esc_html($agent->display_name ?: $agent->user_login) . '</strong>';
        echo '<span class="estate-office-agents__agent-username">' . esc_html($agent->user_email) . '</span>';
        if ($bio !== '') {
            echo '<p class="estate-office-agents__bio">' . esc_html($bio) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</td>';

        echo '<td class="estate-office-agents__contacts">';
        if ($contactParts) {
            foreach ($contactParts as $part) {
                echo '<span>' . $part . '</span>';
            }
        } else {
            echo '—';
        }
        echo '</td>';

        echo '<td class="estate-office-agents__counts">';
        echo '<span class="estate-office-agents__count-total">' . esc_html__('Łącznie:', 'estate-office') . ' ' . esc_html(number_format_i18n($totalCount)) . '</span>';
        foreach ($labels as $key => $label) {
            $count = (int) ($counts[$key] ?? 0);
            echo '<span>' . esc_html($label) . ': ' . esc_html(number_format_i18n($count)) . '</span>';
        }
        echo '</td>';

        echo '<td class="estate-office-agents__actions">';
        $actions = $this->getActions($agent, $counts);
        if ($actions) {
            foreach ($actions as $action) {
                echo $action;
            }
        } else {
            echo '—';
        }
        echo '</td>';
        echo '</tr>';
    }

    /**
     * @param array{properties:int,searches:int,clients:int} $counts
     * @return array<int, string>
     */
    private function getActions(WP_User $agent, array $counts): array
    {
        $actions = [];

        $editLink = get_edit_user_link($agent->ID);
        if ($editLink) {
            $actions[] = '<a class="button button-small" href="' . esc_url($editLink) . '">' . esc_html__('Edytuj profil', 'estate-office') . '</a>';
        }

        $email = sanitize_email($agent->user_email);
        if ($email !== '') {
            $actions[] = '<a class="button button-small" href="' . esc_url('mailto:' . rawurlencode($email)) . '">' . esc_html__('Wyślij e-mail', 'estate-office') . '</a>';
        }

        if (($counts['properties'] ?? 0) > 0) {
            $link = add_query_arg(
                [
                    'post_type'      => PropertyRegister::POST_TYPE,
                    'estate_manager' => $agent->ID,
                ],
                admin_url('edit.php')
            );
            $actions[] = '<a class="button button-small" href="' . esc_url($link) . '">' . esc_html__('Nieruchomości', 'estate-office') . '</a>';
        }

        if (($counts['searches'] ?? 0) > 0) {
            $link = add_query_arg(
                [
                    'post_type'      => SearchRegister::POST_TYPE,
                    'estate_manager' => $agent->ID,
                ],
                admin_url('edit.php')
            );
            $actions[] = '<a class="button button-small" href="' . esc_url($link) . '">' . esc_html__('Poszukiwania', 'estate-office') . '</a>';
        }

        if (($counts['clients'] ?? 0) > 0) {
            $link = add_query_arg(
                [
                    'post_type'      => ClientRegister::POST_TYPE,
                    'estate_manager' => $agent->ID,
                ],
                admin_url('edit.php')
            );
            $actions[] = '<a class="button button-small" href="' . esc_url($link) . '">' . esc_html__('Klienci', 'estate-office') . '</a>';
        }

        return $actions;
    }

    /**
     * @return array<int, string>
     */
    private function getContactDetails(WP_User $agent): array
    {
        $details = [];

        $phone = (string) get_user_meta($agent->ID, AgentProfile::META_PHONE, true);
        if ($phone !== '') {
            $details[] = $this->formatPhone($phone, __('Telefon', 'estate-office'));
        }

        $phoneAlt = (string) get_user_meta($agent->ID, AgentProfile::META_PHONE_ALT, true);
        if ($phoneAlt !== '') {
            $details[] = $this->formatPhone($phoneAlt, __('Telefon dodatkowy', 'estate-office'));
        }

        $officePhone = (string) get_user_meta($agent->ID, AgentProfile::META_OFFICE_PHONE, true);
        if ($officePhone !== '') {
            $details[] = $this->formatPhone($officePhone, __('Telefon biura', 'estate-office'));
        }

        $whatsapp = (string) get_user_meta($agent->ID, AgentProfile::META_WHATSAPP, true);
        if ($whatsapp !== '') {
            $details[] = $this->formatWhatsapp($whatsapp);
        }

        return $details;
    }

    private function formatPhone(string $value, string $label): string
    {
        $href = $this->buildTelHref($value);

        if ($href !== '') {
            return sprintf(
                '%s: <a href="%s">%s</a>',
                esc_html($label),
                esc_url($href),
                esc_html($value)
            );
        }

        return sprintf('%s: %s', esc_html($label), esc_html($value));
    }

    private function formatWhatsapp(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return sprintf(
                '%s: <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_html__('WhatsApp', 'estate-office'),
                esc_url($value),
                esc_html($value)
            );
        }

        $href = $this->buildTelHref($value);

        if ($href !== '') {
            return sprintf(
                '%s: <a href="%s">%s</a>',
                esc_html__('WhatsApp', 'estate-office'),
                esc_url($href),
                esc_html($value)
            );
        }

        return sprintf('%s: %s', esc_html__('WhatsApp', 'estate-office'), esc_html($value));
    }

    private function buildTelHref(string $value): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', $value);

        if ($normalized === '') {
            return '';
        }

        return 'tel:' . $normalized;
    }

    /**
     * @param array<int, WP_User> $agents
     * @return array<int, array{properties:int,searches:int,clients:int}>
     */
    private function getAssignmentCounts(array $agents): array
    {
        $counts = [];

        if (!$agents) {
            return $counts;
        }

        $ids = array_map(static fn(WP_User $user): int => $user->ID, $agents);

        foreach ($ids as $id) {
            $counts[$id] = [
                'properties' => 0,
                'searches'   => 0,
                'clients'    => 0,
            ];
        }

        global $wpdb;

        $sources = [
            'properties' => [
                'meta_key'  => 'estate_property_manager',
                'post_type' => PropertyRegister::POST_TYPE,
            ],
            'searches'   => [
                'meta_key'  => 'estate_search_manager',
                'post_type' => SearchRegister::POST_TYPE,
            ],
            'clients'    => [
                'meta_key'  => 'estate_client_manager',
                'post_type' => ClientRegister::POST_TYPE,
            ],
        ];

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        foreach ($sources as $type => $source) {
            $params = array_merge([
                $source['meta_key'],
                $source['post_type'],
            ], $ids);

            $query = $wpdb->prepare(
                "SELECT CAST(pm.meta_value AS UNSIGNED) AS user_id, COUNT(pm.post_id) AS total
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                    AND p.post_type = %s
                    AND p.post_status NOT IN ('trash', 'auto-draft')
                    AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
                GROUP BY user_id",
                $params
            );

            /** @var array<int, array{user_id:string,total:string}> $results */
            $results = $wpdb->get_results($query, ARRAY_A);

            foreach ($results as $row) {
                $userId = absint($row['user_id']);
                if (!isset($counts[$userId])) {
                    continue;
                }

                $counts[$userId][$type] = (int) $row['total'];
            }
        }

        return $counts;
    }

    private function getPagination(int $total, int $page, int $perPage, string $search): string
    {
        if ($total <= $perPage) {
            return '';
        }

        $totalPages = (int) ceil($total / $perPage);

        $baseArgs = ['page' => 'estate-office-agents'];
        if ($search !== '') {
            $baseArgs['s'] = $search;
        }

        $base = add_query_arg($baseArgs, admin_url('admin.php'));

        $links = paginate_links([
            'base'      => add_query_arg('paged', '%#%', $base),
            'format'    => '',
            'current'   => $page,
            'total'     => $totalPages,
            'type'      => 'plain',
            'prev_text' => __('« Poprzednia', 'estate-office'),
            'next_text' => __('Następna »', 'estate-office'),
        ]);

        return is_string($links) ? $links : '';
    }
}
