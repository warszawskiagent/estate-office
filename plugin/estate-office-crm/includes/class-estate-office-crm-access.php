<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOfficeCRM_Access
{
    /** @var array<int,int> */
    private static array $office_id_cache = [];

    /** @var array<int,array<int,int>> */
    private static array $office_user_ids_cache = [];

    /** @var array<int,bool> */
    private static array $manager_cache = [];

    public static function is_manager_user(int $user_id = 0): bool
    {
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0) {
            return false;
        }

        if (array_key_exists($user_id, self::$manager_cache)) {
            return self::$manager_cache[$user_id];
        }

        $user = get_userdata($user_id);
        if (! $user instanceof WP_User) {
            self::$manager_cache[$user_id] = false;
            return false;
        }

        $roles = is_array($user->roles) ? $user->roles : [];
        $is_manager = in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true);
        self::$manager_cache[$user_id] = $is_manager;

        return $is_manager;
    }

    public static function is_agent_or_manager_user(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (! $user instanceof WP_User) {
            return false;
        }

        $roles = is_array($user->roles) ? $user->roles : [];
        return in_array(EstateOfficeCRM_Installer::ROLE_AGENT, $roles, true)
            || in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true);
    }

    public static function is_valid_owner_user(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        return self::is_agent_or_manager_user($user_id);
    }

    /**
     * @param array<string,string> $tables
     */
    public static function can_access_owner_user(int $owner_user_id, array $tables, int $current_user_id = 0): bool
    {
        if ($current_user_id <= 0) {
            $current_user_id = get_current_user_id();
        }

        if ($current_user_id <= 0) {
            return false;
        }

        if (user_can($current_user_id, 'manage_options')) {
            return true;
        }

        if ($owner_user_id <= 0) {
            return true;
        }

        if ($owner_user_id === $current_user_id) {
            return true;
        }

        if (! self::is_manager_user($current_user_id)) {
            return false;
        }

        $current_office_id = self::get_user_office_id($current_user_id, $tables);
        if ($current_office_id <= 0) {
            return false;
        }

        $owner_office_id = self::get_user_office_id($owner_user_id, $tables);
        return $owner_office_id > 0 && $owner_office_id === $current_office_id;
    }

    /**
     * @param array<string,string> $tables
     * @return array{sql:string,params:array<int,int>}
     */
    public static function build_owner_scope_clause(array $tables, string $owner_column = 'owner_user_id', int $current_user_id = 0): array
    {
        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $owner_column)) {
            $owner_column = 'owner_user_id';
        }

        if ($current_user_id <= 0) {
            $current_user_id = get_current_user_id();
        }

        if ($current_user_id <= 0) {
            return [
                'sql' => ' AND 1=0',
                'params' => [],
            ];
        }

        if (user_can($current_user_id, 'manage_options')) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        if (self::is_manager_user($current_user_id)) {
            $office_id = self::get_user_office_id($current_user_id, $tables);
            if ($office_id > 0) {
                $office_user_ids = self::get_office_user_ids($office_id, $tables);
                if (! empty($office_user_ids)) {
                    if (! in_array($current_user_id, $office_user_ids, true)) {
                        $office_user_ids[] = $current_user_id;
                    }
                    $office_user_ids = array_values(array_unique(array_filter(array_map('absint', $office_user_ids))));
                    if (! empty($office_user_ids)) {
                        $placeholders = implode(',', array_fill(0, count($office_user_ids), '%d'));
                        return [
                            'sql' => " AND {$owner_column} IN ({$placeholders})",
                            'params' => $office_user_ids,
                        ];
                    }
                }
            }
        }

        return [
            'sql' => " AND {$owner_column} = %d",
            'params' => [$current_user_id],
        ];
    }

    /**
     * @param array<string,string> $tables
     */
    public static function get_user_office_id(int $user_id, array $tables): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        if (array_key_exists($user_id, self::$office_id_cache)) {
            return self::$office_id_cache[$user_id];
        }

        if (! isset($tables['agent_profiles'])) {
            self::$office_id_cache[$user_id] = 0;
            return 0;
        }

        global $wpdb;

        $office_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT office_id FROM {$tables['agent_profiles']} WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        self::$office_id_cache[$user_id] = $office_id > 0 ? $office_id : 0;
        return self::$office_id_cache[$user_id];
    }

    /**
     * @param array<string,string> $tables
     * @return array<int,int>
     */
    public static function get_office_user_ids(int $office_id, array $tables): array
    {
        if ($office_id <= 0 || ! isset($tables['agent_profiles'])) {
            return [];
        }

        if (isset(self::$office_user_ids_cache[$office_id])) {
            return self::$office_user_ids_cache[$office_id];
        }

        global $wpdb;

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$tables['agent_profiles']} WHERE office_id = %d",
                $office_id
            )
        );

        if (! is_array($rows) || empty($rows)) {
            self::$office_user_ids_cache[$office_id] = [];
            return [];
        }

        $user_ids = [];
        foreach ($rows as $raw_user_id) {
            $user_id = absint((string) $raw_user_id);
            if ($user_id <= 0) {
                continue;
            }

            if (self::is_agent_or_manager_user($user_id)) {
                $user_ids[] = $user_id;
            }
        }

        $user_ids = array_values(array_unique($user_ids));
        self::$office_user_ids_cache[$office_id] = $user_ids;

        return $user_ids;
    }
}

