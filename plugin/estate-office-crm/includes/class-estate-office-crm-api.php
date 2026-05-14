<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * REST API for EstateOffice CRM mobile application.
 *
 * Authentication uses a bearer token issued by POST /eocrm/v1/auth.
 * The token is stored as a WordPress transient (30-day TTL).
 */
class EstateOfficeCRM_API
{
    const NAMESPACE = 'eocrm/v1';
    const TOKEN_PREFIX = 'eocrm_tok_';
    const TOKEN_TTL = 30 * DAY_IN_SECONDS;

    /** @var array<string,string> */
    private array $tables;

    public function __construct()
    {
        global $wpdb;
        $this->tables = EstateOfficeCRM_DB_Schema::tables($wpdb->prefix);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_auth'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => ['required' => true, 'type' => 'string'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_logout'],
            'permission_callback' => [$this, 'require_auth'],
        ]);

        register_rest_route(self::NAMESPACE, '/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_me'],
            'permission_callback' => [$this, 'require_auth'],
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_dashboard'],
            'permission_callback' => [$this, 'require_auth'],
        ]);

        // Properties
        register_rest_route(self::NAMESPACE, '/properties', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_properties_list'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_property_create'],
                'permission_callback' => [$this, 'require_manage_properties'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/properties/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_property_get'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [$this, 'handle_property_update'],
                'permission_callback' => [$this, 'require_manage_properties'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handle_property_delete'],
                'permission_callback' => [$this, 'require_delete_records'],
            ],
        ]);

        // Clients
        register_rest_route(self::NAMESPACE, '/clients', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_clients_list'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_client_create'],
                'permission_callback' => [$this, 'require_manage_clients'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/clients/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_client_get'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [$this, 'handle_client_update'],
                'permission_callback' => [$this, 'require_manage_clients'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handle_client_delete'],
                'permission_callback' => [$this, 'require_delete_records'],
            ],
        ]);

        // Agreements
        register_rest_route(self::NAMESPACE, '/agreements', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_agreements_list'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_agreement_create'],
                'permission_callback' => [$this, 'require_manage_agreements'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/agreements/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_agreement_get'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [$this, 'handle_agreement_update'],
                'permission_callback' => [$this, 'require_manage_agreements'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handle_agreement_delete'],
                'permission_callback' => [$this, 'require_delete_records'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/agreements/(?P<id>\d+)/stages', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_agreement_add_stage'],
            'permission_callback' => [$this, 'require_manage_agreements'],
        ]);

        // Searches
        register_rest_route(self::NAMESPACE, '/searches', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_searches_list'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_search_create'],
                'permission_callback' => [$this, 'require_manage_searches'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/searches/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle_search_get'],
                'permission_callback' => [$this, 'require_auth'],
            ],
            [
                'methods'             => 'PUT,PATCH',
                'callback'            => [$this, 'handle_search_update'],
                'permission_callback' => [$this, 'require_manage_searches'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handle_search_delete'],
                'permission_callback' => [$this, 'require_delete_records'],
            ],
        ]);

        // Agents list
        register_rest_route(self::NAMESPACE, '/agents', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_agents_list'],
            'permission_callback' => [$this, 'require_auth'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function handle_auth(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $username = sanitize_text_field((string) $request->get_param('username'));
        $password = (string) $request->get_param('password');

        if ($username === '' || $password === '') {
            return new WP_Error('eocrm_auth_missing', 'Podaj login i hasło.', ['status' => 400]);
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error('eocrm_auth_invalid', 'Nieprawidłowy login lub hasło.', ['status' => 401]);
        }

        if (! user_can($user, 'eocrm_access_crm') && ! user_can($user, 'manage_options')) {
            return new WP_Error('eocrm_auth_forbidden', 'Brak dostępu do systemu CRM.', ['status' => 403]);
        }

        $token = wp_generate_password(48, false);
        $transient_key = self::TOKEN_PREFIX . $token;
        set_transient($transient_key, $user->ID, self::TOKEN_TTL);

        $role = $this->resolve_user_role($user);

        return new WP_REST_Response([
            'token'        => $token,
            'user_id'      => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'role'         => $role,
            'expires_in'   => self::TOKEN_TTL,
        ], 200);
    }

    public function handle_logout(WP_REST_Request $request): WP_REST_Response
    {
        $token = $this->extract_token($request);
        if ($token !== '') {
            delete_transient(self::TOKEN_PREFIX . $token);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function handle_me(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $profile = $this->get_agent_profile($user->ID);
        $role    = $this->resolve_user_role($user);

        return new WP_REST_Response([
            'user_id'      => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'role'         => $role,
            'profile'      => $profile,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function handle_dashboard(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        global $wpdb;
        $scope = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $user->ID);

        $p_sql = "SELECT COUNT(*) FROM {$this->tables['properties']} WHERE is_active = 1" . $scope['sql'];
        $a_sql = "SELECT COUNT(*) FROM {$this->tables['agreements']} WHERE is_active = 1" . $scope['sql'];
        $s_sql = "SELECT COUNT(*) FROM {$this->tables['searches']} WHERE is_active = 1" . $scope['sql'];
        $c_sql = "SELECT COUNT(*) FROM {$this->tables['clients']} WHERE is_active = 1" . $scope['sql'];

        $properties_count = (int) (empty($scope['params'])
            ? $wpdb->get_var($p_sql)
            : $wpdb->get_var($wpdb->prepare($p_sql, $scope['params'])));

        $agreements_count = (int) (empty($scope['params'])
            ? $wpdb->get_var($a_sql)
            : $wpdb->get_var($wpdb->prepare($a_sql, $scope['params'])));

        $searches_count = (int) (empty($scope['params'])
            ? $wpdb->get_var($s_sql)
            : $wpdb->get_var($wpdb->prepare($s_sql, $scope['params'])));

        $clients_count = (int) (empty($scope['params'])
            ? $wpdb->get_var($c_sql)
            : $wpdb->get_var($wpdb->prepare($c_sql, $scope['params'])));

        $recent_properties = $this->fetch_properties_list($user->ID, '', 5, 0);
        $recent_agreements = $this->fetch_agreements_list($user->ID, '', 5, 0);

        return new WP_REST_Response([
            'stats' => [
                'properties' => $properties_count,
                'agreements' => $agreements_count,
                'searches'   => $searches_count,
                'clients'    => $clients_count,
            ],
            'recent_properties' => $recent_properties,
            'recent_agreements' => $recent_agreements,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Properties
    // -------------------------------------------------------------------------

    public function handle_properties_list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $search  = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $limit   = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));
        $page    = max(1, (int) ($request->get_param('page') ?? 1));
        $offset  = ($page - 1) * $limit;

        $items = $this->fetch_properties_list($user->ID, $search, $limit, $offset);
        $total = $this->count_properties($user->ID, $search);

        return new WP_REST_Response([
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ], 200);
    }

    public function handle_property_get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_property_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Nieruchomość nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $media = $this->get_property_media($id);
        $row['media'] = $media;

        return new WP_REST_Response($this->format_property($row), 200);
    }

    public function handle_property_create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $data = $this->extract_property_fields($request, $user->ID, true);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $inserted = $wpdb->insert($this->tables['properties'], $data['payload'], $data['formats']);
        if (! $inserted) {
            return new WP_Error('eocrm_db_error', 'Nie udało się zapisać nieruchomości.', ['status' => 500]);
        }

        $id  = (int) $wpdb->insert_id;
        $row = $this->get_property_row($id);

        return new WP_REST_Response($this->format_property($row ?? []), 201);
    }

    public function handle_property_update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_property_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Nieruchomość nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $data = $this->extract_property_fields($request, $user->ID, false);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $wpdb->update($this->tables['properties'], $data['payload'], ['id' => $id], $data['formats'], ['%d']);
        $row = $this->get_property_row($id);

        return new WP_REST_Response($this->format_property($row ?? []), 200);
    }

    public function handle_property_delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_property_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Nieruchomość nie istnieje.', ['status' => 404]);
        }

        global $wpdb;
        $wpdb->update(
            $this->tables['properties'],
            ['is_active' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    // -------------------------------------------------------------------------
    // Clients
    // -------------------------------------------------------------------------

    public function handle_clients_list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $search = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $limit  = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));
        $page   = max(1, (int) ($request->get_param('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $items = $this->fetch_clients_list($user->ID, $search, $limit, $offset);
        $total = $this->count_clients($user->ID, $search);

        return new WP_REST_Response([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ], 200);
    }

    public function handle_client_get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_client_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Klient nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $addresses         = $this->get_client_addresses($id);
        $row['addresses']  = $addresses;
        $row['agreements'] = $this->get_client_agreements($id);

        return new WP_REST_Response($this->format_client($row), 200);
    }

    public function handle_client_create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $data = $this->extract_client_fields($request, $user->ID);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $inserted = $wpdb->insert($this->tables['clients'], $data['payload'], $data['formats']);
        if (! $inserted) {
            return new WP_Error('eocrm_db_error', 'Nie udało się zapisać klienta.', ['status' => 500]);
        }

        $client_id = (int) $wpdb->insert_id;
        $now       = current_time('mysql');
        $this->upsert_client_addresses($client_id, $request, $now);

        $row               = $this->get_client_row($client_id);
        $row['addresses']  = $this->get_client_addresses($client_id);
        $row['agreements'] = [];

        return new WP_REST_Response($this->format_client($row ?? []), 201);
    }

    public function handle_client_update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_client_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Klient nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $data = $this->extract_client_fields($request, $user->ID);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $wpdb->update($this->tables['clients'], $data['payload'], ['id' => $id], $data['formats'], ['%d']);
        $this->upsert_client_addresses($id, $request, current_time('mysql'));

        $row               = $this->get_client_row($id);
        $row['addresses']  = $this->get_client_addresses($id);
        $row['agreements'] = $this->get_client_agreements($id);

        return new WP_REST_Response($this->format_client($row ?? []), 200);
    }

    public function handle_client_delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_client_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Klient nie istnieje.', ['status' => 404]);
        }

        global $wpdb;
        $wpdb->update(
            $this->tables['clients'],
            ['is_active' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    // -------------------------------------------------------------------------
    // Agreements
    // -------------------------------------------------------------------------

    public function handle_agreements_list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $search = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $limit  = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));
        $page   = max(1, (int) ($request->get_param('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $items = $this->fetch_agreements_list($user->ID, $search, $limit, $offset);
        $total = $this->count_agreements($user->ID, $search);

        return new WP_REST_Response([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ], 200);
    }

    public function handle_agreement_get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_agreement_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Umowa nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $row['clients']    = $this->get_agreement_clients($id);
        $row['stages']     = $this->get_agreement_stages($id);
        $row['properties'] = $this->get_agreement_properties($id);
        $row['searches']   = $this->get_agreement_searches($id);

        return new WP_REST_Response($this->format_agreement($row), 200);
    }

    public function handle_agreement_create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $data = $this->extract_agreement_fields($request, $user->ID);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $inserted = $wpdb->insert($this->tables['agreements'], $data['payload'], $data['formats']);
        if (! $inserted) {
            return new WP_Error('eocrm_db_error', 'Nie udało się zapisać umowy.', ['status' => 500]);
        }

        $agreement_id = (int) $wpdb->insert_id;
        $now          = current_time('mysql');

        $wpdb->insert($this->tables['agreement_stages'], [
            'agreement_id' => $agreement_id,
            'stage_name'   => 'Umowa Pośrednictwa',
            'stage_date'   => $data['payload']['date_signed'],
            'created_by'   => $user->ID,
            'created_at'   => $now,
        ]);

        $client_ids = $request->get_param('client_ids');
        if (is_array($client_ids)) {
            foreach (array_map('absint', $client_ids) as $cid) {
                if ($cid > 0) {
                    $wpdb->insert($this->tables['agreement_clients'], [
                        'agreement_id' => $agreement_id,
                        'client_id'    => $cid,
                        'created_at'   => $now,
                    ]);
                }
            }
        }

        $row               = $this->get_agreement_row($agreement_id);
        $row['clients']    = $this->get_agreement_clients($agreement_id);
        $row['stages']     = $this->get_agreement_stages($agreement_id);
        $row['properties'] = [];
        $row['searches']   = [];

        return new WP_REST_Response($this->format_agreement($row ?? []), 201);
    }

    public function handle_agreement_update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_agreement_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Umowa nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $data = $this->extract_agreement_fields($request, $user->ID);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $wpdb->update($this->tables['agreements'], $data['payload'], ['id' => $id], $data['formats'], ['%d']);

        $row               = $this->get_agreement_row($id);
        $row['clients']    = $this->get_agreement_clients($id);
        $row['stages']     = $this->get_agreement_stages($id);
        $row['properties'] = $this->get_agreement_properties($id);
        $row['searches']   = $this->get_agreement_searches($id);

        return new WP_REST_Response($this->format_agreement($row ?? []), 200);
    }

    public function handle_agreement_delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_agreement_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Umowa nie istnieje.', ['status' => 404]);
        }

        global $wpdb;
        $wpdb->update(
            $this->tables['agreements'],
            ['is_active' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    public function handle_agreement_add_stage(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_agreement_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Umowa nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $stage_name = sanitize_text_field((string) ($request->get_param('stage_name') ?? ''));
        $stage_date = sanitize_text_field((string) ($request->get_param('stage_date') ?? ''));

        if ($stage_name === '') {
            return new WP_Error('eocrm_validation', 'Podaj nazwę etapu.', ['status' => 400]);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $stage_date)) {
            return new WP_Error('eocrm_validation', 'Podaj datę etapu w formacie RRRR-MM-DD.', ['status' => 400]);
        }

        $allowed_stages = EstateOfficeCRM_Stages::get_stages();
        $stage_key = sanitize_key($stage_name);
        if (! array_key_exists($stage_key, $allowed_stages) && ! in_array($stage_name, $allowed_stages, true)) {
            $stage_name = sanitize_text_field($stage_name);
        }

        global $wpdb;
        $now = current_time('mysql');

        $wpdb->insert($this->tables['agreement_stages'], [
            'agreement_id' => $id,
            'stage_name'   => $stage_name,
            'stage_date'   => $stage_date,
            'created_by'   => $user->ID,
            'created_at'   => $now,
        ]);

        $wpdb->update(
            $this->tables['agreements'],
            ['current_stage' => $stage_name, 'updated_at' => $now],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return new WP_REST_Response([
            'success'       => true,
            'current_stage' => $stage_name,
            'stages'        => $this->get_agreement_stages($id),
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Searches
    // -------------------------------------------------------------------------

    public function handle_searches_list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $search = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $limit  = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));
        $page   = max(1, (int) ($request->get_param('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $items = $this->fetch_searches_list($user->ID, $search, $limit, $offset);
        $total = $this->count_searches($user->ID, $search);

        return new WP_REST_Response([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ], 200);
    }

    public function handle_search_get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_search_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Poszukiwanie nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $row['clients'] = $this->get_search_clients($id);

        return new WP_REST_Response($this->format_search($row), 200);
    }

    public function handle_search_create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $data = $this->extract_search_fields($request, $user->ID);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $inserted = $wpdb->insert($this->tables['searches'], $data['payload'], $data['formats']);
        if (! $inserted) {
            return new WP_Error('eocrm_db_error', 'Nie udało się zapisać poszukiwania.', ['status' => 500]);
        }

        $search_id = (int) $wpdb->insert_id;
        $now       = current_time('mysql');

        $client_ids = $request->get_param('client_ids');
        if (is_array($client_ids)) {
            foreach (array_map('absint', $client_ids) as $cid) {
                if ($cid > 0) {
                    $wpdb->insert($this->tables['search_clients'], [
                        'search_id' => $search_id,
                        'client_id' => $cid,
                        'created_at' => $now,
                    ]);
                }
            }
        }

        $row            = $this->get_search_row($search_id);
        $row['clients'] = $this->get_search_clients($search_id);

        return new WP_REST_Response($this->format_search($row ?? []), 201);
    }

    public function handle_search_update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_search_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Poszukiwanie nie istnieje.', ['status' => 404]);
        }

        if (! EstateOfficeCRM_Access::can_access_owner_user((int) ($row['owner_user_id'] ?? 0), $this->tables, $user->ID)) {
            return new WP_Error('eocrm_forbidden', 'Brak dostępu.', ['status' => 403]);
        }

        $data = $this->extract_search_fields($request, $user->ID);
        if (is_wp_error($data)) {
            return $data;
        }

        global $wpdb;
        $wpdb->update($this->tables['searches'], $data['payload'], ['id' => $id], $data['formats'], ['%d']);

        $row            = $this->get_search_row($id);
        $row['clients'] = $this->get_search_clients($id);

        return new WP_REST_Response($this->format_search($row ?? []), 200);
    }

    public function handle_search_delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        $id  = (int) $request->get_param('id');
        $row = $this->get_search_row($id);
        if (! $row) {
            return new WP_Error('eocrm_not_found', 'Poszukiwanie nie istnieje.', ['status' => 404]);
        }

        global $wpdb;
        $wpdb->update(
            $this->tables['searches'],
            ['is_active' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    // -------------------------------------------------------------------------
    // Agents
    // -------------------------------------------------------------------------

    public function handle_agents_list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Nieautoryzowany.', ['status' => 401]);
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT u.ID, u.display_name, u.user_email,
                    ap.phone, ap.city, ap.bio, ap.photo_id
             FROM {$wpdb->users} u
             LEFT JOIN {$this->tables['agent_profiles']} ap ON ap.user_id = u.ID
             WHERE u.ID IN (
                 SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = '{$wpdb->prefix}capabilities'
                 AND (meta_value LIKE '%eocrm_agent%' OR meta_value LIKE '%eocrm_manager%' OR meta_value LIKE '%administrator%')
             )
             ORDER BY u.display_name ASC",
            ARRAY_A
        );

        $agents = [];
        foreach ((is_array($rows) ? $rows : []) as $r) {
            $photo_url = '';
            if (! empty($r['photo_id'])) {
                $url = wp_get_attachment_image_url((int) $r['photo_id'], 'thumbnail');
                $photo_url = is_string($url) ? $url : '';
            }

            $agents[] = [
                'user_id'      => (int) $r['ID'],
                'display_name' => $r['display_name'] ?? '',
                'email'        => $r['user_email'] ?? '',
                'phone'        => $r['phone'] ?? '',
                'city'         => $r['city'] ?? '',
                'bio'          => $r['bio'] ?? '',
                'photo_url'    => $photo_url,
            ];
        }

        return new WP_REST_Response(['items' => $agents], 200);
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------

    public function require_auth(WP_REST_Request $request): bool|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Wymagane logowanie.', ['status' => 401]);
        }

        return true;
    }

    public function require_manage_properties(WP_REST_Request $request): bool|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Wymagane logowanie.', ['status' => 401]);
        }

        if (! user_can($user, 'eocrm_manage_properties') && ! user_can($user, 'manage_options')) {
            return new WP_Error('eocrm_forbidden', 'Brak uprawnień.', ['status' => 403]);
        }

        return true;
    }

    public function require_manage_clients(WP_REST_Request $request): bool|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Wymagane logowanie.', ['status' => 401]);
        }

        if (! user_can($user, 'eocrm_manage_clients') && ! user_can($user, 'manage_options')) {
            return new WP_Error('eocrm_forbidden', 'Brak uprawnień.', ['status' => 403]);
        }

        return true;
    }

    public function require_manage_agreements(WP_REST_Request $request): bool|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Wymagane logowanie.', ['status' => 401]);
        }

        if (! user_can($user, 'eocrm_manage_agreements') && ! user_can($user, 'manage_options')) {
            return new WP_Error('eocrm_forbidden', 'Brak uprawnień.', ['status' => 403]);
        }

        return true;
    }

    public function require_manage_searches(WP_REST_Request $request): bool|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Wymagane logowanie.', ['status' => 401]);
        }

        if (! user_can($user, 'eocrm_manage_searches') && ! user_can($user, 'manage_options')) {
            return new WP_Error('eocrm_forbidden', 'Brak uprawnień.', ['status' => 403]);
        }

        return true;
    }

    public function require_delete_records(WP_REST_Request $request): bool|WP_Error
    {
        $user = $this->get_request_user($request);
        if (! $user instanceof WP_User) {
            return new WP_Error('eocrm_auth_required', 'Wymagane logowanie.', ['status' => 401]);
        }

        if (! user_can($user, 'eocrm_delete_records') && ! user_can($user, 'manage_options')) {
            return new WP_Error('eocrm_forbidden', 'Brak uprawnień do usuwania.', ['status' => 403]);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Token helpers
    // -------------------------------------------------------------------------

    private function extract_token(WP_REST_Request $request): string
    {
        $auth_header = $request->get_header('authorization');
        if (is_string($auth_header) && str_starts_with($auth_header, 'Bearer ')) {
            return trim(substr($auth_header, 7));
        }

        return '';
    }

    private function get_request_user(WP_REST_Request $request): ?WP_User
    {
        $token = $this->extract_token($request);
        if ($token === '') {
            return null;
        }

        $user_id = (int) get_transient(self::TOKEN_PREFIX . $token);
        if ($user_id <= 0) {
            return null;
        }

        $user = get_userdata($user_id);
        return $user instanceof WP_User ? $user : null;
    }

    // -------------------------------------------------------------------------
    // Data fetchers
    // -------------------------------------------------------------------------

    /** @return array<string,mixed>[] */
    private function fetch_properties_list(int $user_id, string $search, int $limit, int $offset): array
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'p.owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE p.is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (p.offer_number LIKE %s OR p.city LIKE %s OR p.street LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $sql = "SELECT p.id, p.offer_number, p.transaction_type, p.property_type,
                       p.street, p.building_no, p.apartment_no, p.city, p.district,
                       p.price, p.price_currency, p.area, p.price_per_m2,
                       p.rooms, p.floor_no, p.owner_user_id,
                       p.is_sold, p.is_rented, p.is_new_offer, p.is_premium,
                       p.export_www, p.current_stage, p.created_at, p.updated_at,
                       u.display_name AS owner_name,
                       pm.media_url AS primary_photo
                FROM {$this->tables['properties']} p
                LEFT JOIN {$wpdb->users} u ON u.ID = p.owner_user_id
                LEFT JOIN {$this->tables['property_media']} pm ON pm.property_id = p.id AND pm.is_primary = 1 AND pm.media_type = 'photo'
                {$where}
                ORDER BY p.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? array_map([$this, 'format_property_row'], $rows) : [];
    }

    private function count_properties(int $user_id, string $search): int
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (offer_number LIKE %s OR city LIKE %s OR street LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $sql = "SELECT COUNT(*) FROM {$this->tables['properties']} {$where}";

        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /** @return array<string,mixed>[]|null */
    private function get_property_row(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.*, u.display_name AS owner_name
                 FROM {$this->tables['properties']} p
                 LEFT JOIN {$wpdb->users} u ON u.ID = p.owner_user_id
                 WHERE p.id = %d AND p.is_active = 1 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>[] */
    private function get_property_media(int $property_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, media_type, media_url, attachment_id, position, is_primary
                 FROM {$this->tables['property_media']}
                 WHERE property_id = %d
                 ORDER BY media_type, position ASC",
                $property_id
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(static function (array $r): array {
            if (empty($r['media_url']) && ! empty($r['attachment_id'])) {
                $url = wp_get_attachment_url((int) $r['attachment_id']);
                $r['media_url'] = is_string($url) ? $url : '';
            }
            return $r;
        }, $rows);
    }

    /** @return array<string,mixed>[] */
    private function fetch_clients_list(int $user_id, string $search, int $limit, int $offset): array
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'c.owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE c.is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.company_name LIKE %s OR c.phone LIKE %s OR c.email LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        $sql = "SELECT c.id, c.client_type, c.first_name, c.last_name,
                       c.company_name, c.representative_name,
                       c.phone, c.email, c.owner_user_id,
                       c.created_at, c.updated_at,
                       u.display_name AS owner_name,
                       ca.city AS address_city, ca.street AS address_street
                FROM {$this->tables['clients']} c
                LEFT JOIN {$wpdb->users} u ON u.ID = c.owner_user_id
                LEFT JOIN {$this->tables['client_addresses']} ca ON ca.client_id = c.id AND ca.address_type = 'main'
                {$where}
                ORDER BY c.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? array_map([$this, 'format_client_row'], $rows) : [];
    }

    private function count_clients(int $user_id, string $search): int
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (first_name LIKE %s OR last_name LIKE %s OR company_name LIKE %s OR phone LIKE %s OR email LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        $sql = "SELECT COUNT(*) FROM {$this->tables['clients']} {$where}";

        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /** @return array<string,mixed>|null */
    private function get_client_row(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, u.display_name AS owner_name
                 FROM {$this->tables['clients']} c
                 LEFT JOIN {$wpdb->users} u ON u.ID = c.owner_user_id
                 WHERE c.id = %d AND c.is_active = 1 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>[] */
    private function get_client_addresses(int $client_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['client_addresses']} WHERE client_id = %d",
                $client_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>[] */
    private function get_client_agreements(int $client_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.agreement_number, a.transaction_type, a.current_stage,
                        a.date_signed, a.date_end, a.is_indefinite
                 FROM {$this->tables['agreements']} a
                 JOIN {$this->tables['agreement_clients']} ac ON ac.agreement_id = a.id
                 WHERE ac.client_id = %d AND a.is_active = 1
                 ORDER BY a.date_signed DESC",
                $client_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>[] */
    private function fetch_agreements_list(int $user_id, string $search, int $limit, int $offset): array
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'a.owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE a.is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (a.agreement_number LIKE %s OR a.transaction_type LIKE %s OR a.current_stage LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $sql = "SELECT a.id, a.agreement_number, a.transaction_type,
                       a.date_signed, a.date_end, a.is_indefinite,
                       a.current_stage, a.commission_amount, a.commission_unit,
                       a.owner_user_id, a.created_at, a.updated_at,
                       u.display_name AS owner_name
                FROM {$this->tables['agreements']} a
                LEFT JOIN {$wpdb->users} u ON u.ID = a.owner_user_id
                {$where}
                ORDER BY a.date_signed DESC
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? array_map([$this, 'format_agreement_row'], $rows) : [];
    }

    private function count_agreements(int $user_id, string $search): int
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (agreement_number LIKE %s OR transaction_type LIKE %s OR current_stage LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $sql = "SELECT COUNT(*) FROM {$this->tables['agreements']} {$where}";

        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /** @return array<string,mixed>|null */
    private function get_agreement_row(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, u.display_name AS owner_name
                 FROM {$this->tables['agreements']} a
                 LEFT JOIN {$wpdb->users} u ON u.ID = a.owner_user_id
                 WHERE a.id = %d AND a.is_active = 1 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>[] */
    private function get_agreement_clients(int $agreement_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id, c.client_type, c.first_name, c.last_name,
                        c.company_name, c.phone, c.email, ac.relation_role
                 FROM {$this->tables['clients']} c
                 JOIN {$this->tables['agreement_clients']} ac ON ac.client_id = c.id
                 WHERE ac.agreement_id = %d AND c.is_active = 1",
                $agreement_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>[] */
    private function get_agreement_stages(int $agreement_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, stage_name, stage_date, created_at
                 FROM {$this->tables['agreement_stages']}
                 WHERE agreement_id = %d
                 ORDER BY stage_date ASC, created_at ASC",
                $agreement_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>[] */
    private function get_agreement_properties(int $agreement_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, offer_number, property_type, street, building_no, city, price, price_currency, area
                 FROM {$this->tables['properties']}
                 WHERE agreement_id = %d AND is_active = 1",
                $agreement_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>[] */
    private function get_agreement_searches(int $agreement_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, search_number, transaction_type, property_type, budget_from, budget_to, location_text
                 FROM {$this->tables['searches']}
                 WHERE agreement_id = %d AND is_active = 1",
                $agreement_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>[] */
    private function fetch_searches_list(int $user_id, string $search, int $limit, int $offset): array
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 's.owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE s.is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (s.search_number LIKE %s OR s.location_text LIKE %s OR s.property_type LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $sql = "SELECT s.id, s.search_number, s.transaction_type, s.property_type,
                       s.budget_from, s.budget_to, s.area_from, s.area_to,
                       s.rooms_from, s.rooms_to, s.location_text,
                       s.owner_user_id, s.created_at, s.updated_at,
                       u.display_name AS owner_name
                FROM {$this->tables['searches']} s
                LEFT JOIN {$wpdb->users} u ON u.ID = s.owner_user_id
                {$where}
                ORDER BY s.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? array_map([$this, 'format_search_row'], $rows) : [];
    }

    private function count_searches(int $user_id, string $search): int
    {
        global $wpdb;
        $scope  = EstateOfficeCRM_Access::build_owner_scope_clause($this->tables, 'owner_user_id', $user_id);
        $params = $scope['params'];
        $where  = 'WHERE is_active = 1' . $scope['sql'];

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where  .= ' AND (search_number LIKE %s OR location_text LIKE %s OR property_type LIKE %s)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $sql = "SELECT COUNT(*) FROM {$this->tables['searches']} {$where}";

        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /** @return array<string,mixed>|null */
    private function get_search_row(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, u.display_name AS owner_name
                 FROM {$this->tables['searches']} s
                 LEFT JOIN {$wpdb->users} u ON u.ID = s.owner_user_id
                 WHERE s.id = %d AND s.is_active = 1 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>[] */
    private function get_search_clients(int $search_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id, c.client_type, c.first_name, c.last_name, c.company_name, c.phone, c.email
                 FROM {$this->tables['clients']} c
                 JOIN {$this->tables['search_clients']} sc ON sc.client_id = c.id
                 WHERE sc.search_id = %d AND c.is_active = 1",
                $search_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    // -------------------------------------------------------------------------
    // Field extractors
    // -------------------------------------------------------------------------

    /**
     * @return array{payload:array<string,mixed>,formats:string[]}|WP_Error
     */
    private function extract_property_fields(WP_REST_Request $request, int $user_id, bool $is_create): array|WP_Error
    {
        $now = current_time('mysql');

        $transaction_type = $this->sanitize_allowed(
            (string) ($request->get_param('transaction_type') ?? ''),
            ['SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM']
        );

        $property_type = $this->sanitize_allowed(
            (string) ($request->get_param('property_type') ?? ''),
            ['MIESZKANIE', 'DOM', 'DZIALKA', 'LOKAL']
        );

        if ($is_create) {
            if ($transaction_type === '') {
                return new WP_Error('eocrm_validation', 'Podaj typ transakcji.', ['status' => 400]);
            }
            if ($property_type === '') {
                return new WP_Error('eocrm_validation', 'Podaj rodzaj nieruchomości.', ['status' => 400]);
            }
        }

        $price   = $request->get_param('price') !== null ? (float) $request->get_param('price') : null;
        $area    = $request->get_param('area') !== null ? (float) $request->get_param('area') : null;
        $price_per_m2 = ($price !== null && $area !== null && $area > 0) ? round($price / $area, 2) : null;

        $tags_json = null;
        $tags_raw  = $request->get_param('tags');
        if (is_array($tags_raw)) {
            $tags_json = wp_json_encode(array_map('sanitize_text_field', $tags_raw));
        }

        $payload = [
            'transaction_type'     => $transaction_type,
            'property_type'        => $property_type,
            'house_type'           => sanitize_text_field((string) ($request->get_param('house_type') ?? '')),
            'street'               => sanitize_text_field((string) ($request->get_param('street') ?? '')),
            'building_no'          => sanitize_text_field((string) ($request->get_param('building_no') ?? '')),
            'apartment_no'         => sanitize_text_field((string) ($request->get_param('apartment_no') ?? '')),
            'postal_code'          => sanitize_text_field((string) ($request->get_param('postal_code') ?? '')),
            'city'                 => sanitize_text_field((string) ($request->get_param('city') ?? '')),
            'district'             => sanitize_text_field((string) ($request->get_param('district') ?? '')),
            'legal_status'         => sanitize_text_field((string) ($request->get_param('legal_status') ?? '')),
            'price'                => $price,
            'price_currency'       => $this->sanitize_allowed((string) ($request->get_param('price_currency') ?? 'PLN'), ['PLN', 'EUR', 'USD']),
            'area'                 => $area,
            'price_per_m2'         => $price_per_m2,
            'admin_rent'           => $request->get_param('admin_rent') !== null ? (float) $request->get_param('admin_rent') : null,
            'rooms'                => $request->get_param('rooms') !== null ? (int) $request->get_param('rooms') : null,
            'bedrooms'             => $request->get_param('bedrooms') !== null ? (int) $request->get_param('bedrooms') : null,
            'bathrooms'            => $request->get_param('bathrooms') !== null ? (int) $request->get_param('bathrooms') : null,
            'floor_no'             => $request->get_param('floor_no') !== null ? (int) $request->get_param('floor_no') : null,
            'floors_total'         => $request->get_param('floors_total') !== null ? (int) $request->get_param('floors_total') : null,
            'year_built'           => $request->get_param('year_built') !== null ? (int) $request->get_param('year_built') : null,
            'description'          => wp_kses_post((string) ($request->get_param('description') ?? '')),
            'is_new_offer'         => (int) (bool) $request->get_param('is_new_offer'),
            'is_exclusive'         => (int) (bool) $request->get_param('is_exclusive'),
            'is_sold'              => (int) (bool) $request->get_param('is_sold'),
            'is_rented'            => (int) (bool) $request->get_param('is_rented'),
            'is_new_price'         => (int) (bool) $request->get_param('is_new_price'),
            'no_commission'        => (int) (bool) $request->get_param('no_commission'),
            'is_mls_offer'         => (int) (bool) $request->get_param('is_mls_offer'),
            'is_premium'           => (int) (bool) $request->get_param('is_premium'),
            'export_www'           => (int) (bool) $request->get_param('export_www'),
            'tags_json'            => $tags_json,
            'updated_at'           => $now,
        ];

        if ($is_create) {
            $offer_number = sanitize_text_field((string) ($request->get_param('offer_number') ?? ''));
            if ($offer_number === '') {
                $offer_number = EstateOfficeCRM_Numbering::generate_offer_number();
            }
            $payload['offer_number']  = $offer_number;
            $payload['owner_user_id'] = $user_id;
            $payload['created_by']    = $user_id;
            $payload['is_active']     = 1;
            $payload['created_at']    = $now;
        }

        $payload = array_filter($payload, static fn($v) => $v !== null);
        $formats = array_map(static function ($v): string {
            if (is_int($v)) return '%d';
            if (is_float($v)) return '%f';
            return '%s';
        }, $payload);

        return ['payload' => $payload, 'formats' => array_values($formats)];
    }

    /**
     * @return array{payload:array<string,mixed>,formats:string[]}|WP_Error
     */
    private function extract_client_fields(WP_REST_Request $request, int $user_id): array|WP_Error
    {
        $now         = current_time('mysql');
        $client_type = $this->sanitize_allowed(
            (string) ($request->get_param('client_type') ?? 'person'),
            ['person', 'company']
        );
        if ($client_type === '') {
            $client_type = 'person';
        }

        if ($client_type === 'person') {
            $first_name = sanitize_text_field((string) ($request->get_param('first_name') ?? ''));
            $last_name  = sanitize_text_field((string) ($request->get_param('last_name') ?? ''));
            if ($first_name === '' || $last_name === '') {
                return new WP_Error('eocrm_validation', 'Imię i nazwisko są wymagane.', ['status' => 400]);
            }
        } else {
            $company_name = sanitize_text_field((string) ($request->get_param('company_name') ?? ''));
            if ($company_name === '') {
                return new WP_Error('eocrm_validation', 'Nazwa firmy jest wymagana.', ['status' => 400]);
            }
        }

        $phone = sanitize_text_field((string) ($request->get_param('phone') ?? ''));
        if ($phone === '') {
            return new WP_Error('eocrm_validation', 'Numer telefonu jest wymagany.', ['status' => 400]);
        }

        $payload = [
            'client_type'          => $client_type,
            'first_name'           => $client_type === 'person' ? sanitize_text_field((string) ($request->get_param('first_name') ?? '')) : '',
            'last_name'            => $client_type === 'person' ? sanitize_text_field((string) ($request->get_param('last_name') ?? '')) : '',
            'company_name'         => $client_type === 'company' ? sanitize_text_field((string) ($request->get_param('company_name') ?? '')) : '',
            'representative_name'  => $client_type === 'company' ? sanitize_text_field((string) ($request->get_param('representative_name') ?? '')) : '',
            'phone'                => $phone,
            'email'                => sanitize_email((string) ($request->get_param('email') ?? '')),
            'website'              => $client_type === 'company' ? esc_url_raw((string) ($request->get_param('website') ?? '')) : '',
            'pesel'                => $client_type === 'person' ? sanitize_text_field((string) ($request->get_param('pesel') ?? '')) : '',
            'document_type'        => $client_type === 'person' ? $this->sanitize_allowed((string) ($request->get_param('document_type') ?? ''), ['dowod_osobisty', 'paszport', 'karta_pobytu']) : '',
            'document_number'      => $client_type === 'person' ? sanitize_text_field((string) ($request->get_param('document_number') ?? '')) : '',
            'nip'                  => $client_type === 'company' ? sanitize_text_field((string) ($request->get_param('nip') ?? '')) : '',
            'krs'                  => $client_type === 'company' ? sanitize_text_field((string) ($request->get_param('krs') ?? '')) : '',
            'regon'                => $client_type === 'company' ? sanitize_text_field((string) ($request->get_param('regon') ?? '')) : '',
            'owner_user_id'        => $user_id,
            'is_active'            => 1,
            'updated_at'           => $now,
            'created_at'           => $now,
        ];

        $formats = array_map(static function ($v): string {
            return is_int($v) ? '%d' : '%s';
        }, $payload);

        return ['payload' => $payload, 'formats' => array_values($formats)];
    }

    private function upsert_client_addresses(int $client_id, WP_REST_Request $request, string $now): void
    {
        global $wpdb;
        $table = $this->tables['client_addresses'];

        $addr_types = ['main', 'correspondence'];
        foreach ($addr_types as $addr_type) {
            $prefix = $addr_type === 'main' ? 'address_' : 'corr_';

            $same = (bool) $request->get_param('correspondence_same');
            if ($addr_type === 'correspondence' && $same) {
                $prefix = 'address_';
            }

            $payload = [
                'street'      => sanitize_text_field((string) ($request->get_param($prefix . 'street') ?? '')),
                'building_no' => sanitize_text_field((string) ($request->get_param($prefix . 'building_no') ?? '')),
                'apartment_no' => sanitize_text_field((string) ($request->get_param($prefix . 'apartment_no') ?? '')),
                'postal_code' => sanitize_text_field((string) ($request->get_param($prefix . 'postal_code') ?? '')),
                'city'        => sanitize_text_field((string) ($request->get_param($prefix . 'city') ?? '')),
                'country'     => sanitize_text_field((string) ($request->get_param($prefix . 'country') ?? 'Polska')),
                'updated_at'  => $now,
            ];

            $existing_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE client_id = %d AND address_type = %s LIMIT 1",
                    $client_id,
                    $addr_type
                )
            );

            if ($existing_id > 0) {
                $wpdb->update($table, $payload, ['id' => $existing_id]);
            } else {
                $payload['client_id']    = $client_id;
                $payload['address_type'] = $addr_type;
                $payload['created_at']   = $now;
                $wpdb->insert($table, $payload);
            }
        }
    }

    /**
     * @return array{payload:array<string,mixed>,formats:string[]}|WP_Error
     */
    private function extract_agreement_fields(WP_REST_Request $request, int $user_id): array|WP_Error
    {
        $now = current_time('mysql');

        $transaction_type = $this->sanitize_allowed(
            (string) ($request->get_param('transaction_type') ?? ''),
            ['SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM']
        );

        if ($transaction_type === '') {
            return new WP_Error('eocrm_validation', 'Podaj typ transakcji.', ['status' => 400]);
        }

        $date_signed = sanitize_text_field((string) ($request->get_param('date_signed') ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_signed)) {
            return new WP_Error('eocrm_validation', 'Podaj datę zawarcia w formacie RRRR-MM-DD.', ['status' => 400]);
        }

        $is_indefinite = (int) (bool) $request->get_param('is_indefinite');
        $date_end      = $is_indefinite ? null : sanitize_text_field((string) ($request->get_param('date_end') ?? ''));

        $commission_stages = $request->get_param('commission_stages');
        $commission_json   = is_array($commission_stages) ? wp_json_encode($commission_stages) : null;

        $payload = [
            'transaction_type'          => $transaction_type,
            'date_signed'               => $date_signed,
            'date_end'                  => $date_end,
            'is_indefinite'             => $is_indefinite,
            'is_exclusive'              => (int) (bool) $request->get_param('is_exclusive'),
            'commission_amount'         => $request->get_param('commission_amount') !== null ? (float) $request->get_param('commission_amount') : null,
            'commission_unit'           => $this->sanitize_allowed((string) ($request->get_param('commission_unit') ?? '%'), ['%', 'PLN', 'EUR', 'USD']),
            'commission_split_enabled'  => (int) (bool) $request->get_param('commission_split_enabled'),
            'commission_stages_json'    => $commission_json,
            'current_stage'             => sanitize_text_field((string) ($request->get_param('current_stage') ?? 'Umowa Pośrednictwa')),
            'owner_user_id'             => $user_id,
            'created_by'                => $user_id,
            'is_active'                 => 1,
            'updated_at'                => $now,
            'created_at'                => $now,
        ];

        $agreement_number = sanitize_text_field((string) ($request->get_param('agreement_number') ?? ''));
        if ($agreement_number !== '') {
            $payload['agreement_number'] = $agreement_number;
        } else {
            $payload['agreement_number'] = EstateOfficeCRM_Numbering::generate_agreement_number();
        }

        $payload = array_filter($payload, static fn($v) => $v !== null);
        $formats = array_map(static function ($v): string {
            if (is_int($v)) return '%d';
            if (is_float($v)) return '%f';
            return '%s';
        }, $payload);

        return ['payload' => $payload, 'formats' => array_values($formats)];
    }

    /**
     * @return array{payload:array<string,mixed>,formats:string[]}|WP_Error
     */
    private function extract_search_fields(WP_REST_Request $request, int $user_id): array|WP_Error
    {
        $now = current_time('mysql');

        $transaction_type = $this->sanitize_allowed(
            (string) ($request->get_param('transaction_type') ?? ''),
            ['SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM']
        );

        if ($transaction_type === '') {
            return new WP_Error('eocrm_validation', 'Podaj typ transakcji.', ['status' => 400]);
        }

        $criteria_raw  = $request->get_param('criteria');
        $criteria_json = is_array($criteria_raw) ? wp_json_encode($criteria_raw) : null;

        $payload = [
            'transaction_type' => $transaction_type,
            'property_type'    => sanitize_text_field((string) ($request->get_param('property_type') ?? '')),
            'budget_from'      => $request->get_param('budget_from') !== null ? (float) $request->get_param('budget_from') : null,
            'budget_to'        => $request->get_param('budget_to') !== null ? (float) $request->get_param('budget_to') : null,
            'area_from'        => $request->get_param('area_from') !== null ? (float) $request->get_param('area_from') : null,
            'area_to'          => $request->get_param('area_to') !== null ? (float) $request->get_param('area_to') : null,
            'rooms_from'       => $request->get_param('rooms_from') !== null ? (int) $request->get_param('rooms_from') : null,
            'rooms_to'         => $request->get_param('rooms_to') !== null ? (int) $request->get_param('rooms_to') : null,
            'location_text'    => sanitize_text_field((string) ($request->get_param('location_text') ?? '')),
            'description'      => wp_kses_post((string) ($request->get_param('description') ?? '')),
            'criteria_json'    => $criteria_json,
            'owner_user_id'    => $user_id,
            'created_by'       => $user_id,
            'is_active'        => 1,
            'updated_at'       => $now,
            'created_at'       => $now,
        ];

        $search_number = sanitize_text_field((string) ($request->get_param('search_number') ?? ''));
        if ($search_number !== '') {
            $payload['search_number'] = $search_number;
        } else {
            $payload['search_number'] = EstateOfficeCRM_Numbering::generate_search_number();
        }

        $payload = array_filter($payload, static fn($v) => $v !== null);
        $formats = array_map(static function ($v): string {
            if (is_int($v)) return '%d';
            if (is_float($v)) return '%f';
            return '%s';
        }, $payload);

        return ['payload' => $payload, 'formats' => array_values($formats)];
    }

    // -------------------------------------------------------------------------
    // Formatters
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $row */
    private function format_property(array $row): array
    {
        return array_merge($this->format_property_row($row), [
            'description'          => $row['description'] ?? '',
            'land_registry_no'     => $row['land_registry_no'] ?? '',
            'no_land_registry'     => (bool) ($row['no_land_registry'] ?? false),
            'latitude'             => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude'            => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'admin_rent'           => $row['admin_rent'] !== null ? (float) $row['admin_rent'] : null,
            'bedrooms'             => $row['bedrooms'] !== null ? (int) $row['bedrooms'] : null,
            'bathrooms'            => $row['bathrooms'] !== null ? (int) $row['bathrooms'] : null,
            'toilets'              => $row['toilets'] !== null ? (int) $row['toilets'] : null,
            'year_built'           => $row['year_built'] !== null ? (int) $row['year_built'] : null,
            'floors_total'         => $row['floors_total'] !== null ? (int) $row['floors_total'] : null,
            'county'               => $row['county'] ?? '',
            'district'             => $row['district'] ?? '',
            'plot_number'          => $row['plot_number'] ?? '',
            'legal_status'         => $row['legal_status'] ?? '',
            'building_finish'      => $row['building_finish'] ?? '',
            'house_type'           => $row['house_type'] ?? '',
            'plot_shape'           => $row['plot_shape'] ?? '',
            'plot_area'            => $row['plot_area'] !== null ? (float) $row['plot_area'] : null,
            'exposure_json'        => $this->decode_json_field($row['exposure_json'] ?? ''),
            'view_json'            => $this->decode_json_field($row['view_json'] ?? ''),
            'layout_json'          => $this->decode_json_field($row['layout_json'] ?? ''),
            'kitchen_type'         => $row['kitchen_type'] ?? '',
            'parking_json'         => $this->decode_json_field($row['parking_json'] ?? ''),
            'media_json'           => $this->decode_json_field($row['media_json'] ?? ''),
            'amenities_json'       => $this->decode_json_field($row['amenities_json'] ?? ''),
            'equipment_json'       => $this->decode_json_field($row['equipment_json'] ?? ''),
            'extra_areas_json'     => $this->decode_json_field($row['extra_areas_json'] ?? ''),
            'tags_json'            => $this->decode_json_field($row['tags_json'] ?? ''),
            'is_exclusive'         => (bool) ($row['is_exclusive'] ?? false),
            'no_commission'        => (bool) ($row['no_commission'] ?? false),
            'is_mls_offer'         => (bool) ($row['is_mls_offer'] ?? false),
            'is_premium'           => (bool) ($row['is_premium'] ?? false),
            'export_portals'       => (bool) ($row['export_portals'] ?? false),
            'agreement_id'         => $row['agreement_id'] !== null ? (int) $row['agreement_id'] : null,
            'media'                => $row['media'] ?? [],
        ]);
    }

    /** @param array<string,mixed> $row */
    private function format_property_row(array $row): array
    {
        return [
            'id'               => (int) ($row['id'] ?? 0),
            'offer_number'     => $row['offer_number'] ?? '',
            'transaction_type' => $row['transaction_type'] ?? '',
            'property_type'    => $row['property_type'] ?? '',
            'street'           => $row['street'] ?? '',
            'building_no'      => $row['building_no'] ?? '',
            'apartment_no'     => $row['apartment_no'] ?? '',
            'postal_code'      => $row['postal_code'] ?? '',
            'city'             => $row['city'] ?? '',
            'price'            => $row['price'] !== null ? (float) $row['price'] : null,
            'price_currency'   => $row['price_currency'] ?? 'PLN',
            'area'             => $row['area'] !== null ? (float) $row['area'] : null,
            'price_per_m2'     => $row['price_per_m2'] !== null ? (float) $row['price_per_m2'] : null,
            'rooms'            => $row['rooms'] !== null ? (int) $row['rooms'] : null,
            'floor_no'         => $row['floor_no'] !== null ? (int) $row['floor_no'] : null,
            'owner_user_id'    => (int) ($row['owner_user_id'] ?? 0),
            'owner_name'       => $row['owner_name'] ?? '',
            'is_sold'          => (bool) ($row['is_sold'] ?? false),
            'is_rented'        => (bool) ($row['is_rented'] ?? false),
            'is_new_offer'     => (bool) ($row['is_new_offer'] ?? false),
            'is_new_price'     => (bool) ($row['is_new_price'] ?? false),
            'export_www'       => (bool) ($row['export_www'] ?? false),
            'primary_photo'    => $row['primary_photo'] ?? '',
            'created_at'       => $row['created_at'] ?? '',
            'updated_at'       => $row['updated_at'] ?? '',
        ];
    }

    /** @param array<string,mixed> $row */
    private function format_client(array $row): array
    {
        return array_merge($this->format_client_row($row), [
            'website'             => $row['website'] ?? '',
            'pesel'               => $row['pesel'] ?? '',
            'document_type'       => $row['document_type'] ?? '',
            'document_number'     => $row['document_number'] ?? '',
            'nip'                 => $row['nip'] ?? '',
            'krs'                 => $row['krs'] ?? '',
            'regon'               => $row['regon'] ?? '',
            'representative_name' => $row['representative_name'] ?? '',
            'addresses'           => $row['addresses'] ?? [],
            'agreements'          => $row['agreements'] ?? [],
        ]);
    }

    /** @param array<string,mixed> $row */
    private function format_client_row(array $row): array
    {
        return [
            'id'           => (int) ($row['id'] ?? 0),
            'client_type'  => $row['client_type'] ?? 'person',
            'first_name'   => $row['first_name'] ?? '',
            'last_name'    => $row['last_name'] ?? '',
            'company_name' => $row['company_name'] ?? '',
            'phone'        => $row['phone'] ?? '',
            'email'        => $row['email'] ?? '',
            'owner_user_id' => (int) ($row['owner_user_id'] ?? 0),
            'owner_name'   => $row['owner_name'] ?? '',
            'address_city' => $row['address_city'] ?? '',
            'address_street' => $row['address_street'] ?? '',
            'created_at'   => $row['created_at'] ?? '',
            'updated_at'   => $row['updated_at'] ?? '',
        ];
    }

    /** @param array<string,mixed> $row */
    private function format_agreement(array $row): array
    {
        return array_merge($this->format_agreement_row($row), [
            'is_exclusive'             => (bool) ($row['is_exclusive'] ?? false),
            'commission_split_enabled' => (bool) ($row['commission_split_enabled'] ?? false),
            'commission_stages_json'   => $this->decode_json_field($row['commission_stages_json'] ?? ''),
            'clients'                  => $row['clients'] ?? [],
            'stages'                   => $row['stages'] ?? [],
            'properties'               => $row['properties'] ?? [],
            'searches'                 => $row['searches'] ?? [],
        ]);
    }

    /** @param array<string,mixed> $row */
    private function format_agreement_row(array $row): array
    {
        return [
            'id'                => (int) ($row['id'] ?? 0),
            'agreement_number'  => $row['agreement_number'] ?? '',
            'transaction_type'  => $row['transaction_type'] ?? '',
            'date_signed'       => $row['date_signed'] ?? '',
            'date_end'          => $row['date_end'] ?? null,
            'is_indefinite'     => (bool) ($row['is_indefinite'] ?? false),
            'current_stage'     => $row['current_stage'] ?? '',
            'commission_amount' => $row['commission_amount'] !== null ? (float) $row['commission_amount'] : null,
            'commission_unit'   => $row['commission_unit'] ?? '%',
            'owner_user_id'     => (int) ($row['owner_user_id'] ?? 0),
            'owner_name'        => $row['owner_name'] ?? '',
            'created_at'        => $row['created_at'] ?? '',
            'updated_at'        => $row['updated_at'] ?? '',
        ];
    }

    /** @param array<string,mixed> $row */
    private function format_search(array $row): array
    {
        return array_merge($this->format_search_row($row), [
            'floor_from'    => $row['floor_from'] !== null ? (int) $row['floor_from'] : null,
            'floor_to'      => $row['floor_to'] !== null ? (int) $row['floor_to'] : null,
            'description'   => $row['description'] ?? '',
            'criteria_json' => $this->decode_json_field($row['criteria_json'] ?? ''),
            'clients'       => $row['clients'] ?? [],
        ]);
    }

    /** @param array<string,mixed> $row */
    private function format_search_row(array $row): array
    {
        return [
            'id'               => (int) ($row['id'] ?? 0),
            'search_number'    => $row['search_number'] ?? '',
            'transaction_type' => $row['transaction_type'] ?? '',
            'property_type'    => $row['property_type'] ?? '',
            'budget_from'      => $row['budget_from'] !== null ? (float) $row['budget_from'] : null,
            'budget_to'        => $row['budget_to'] !== null ? (float) $row['budget_to'] : null,
            'area_from'        => $row['area_from'] !== null ? (float) $row['area_from'] : null,
            'area_to'          => $row['area_to'] !== null ? (float) $row['area_to'] : null,
            'rooms_from'       => $row['rooms_from'] !== null ? (int) $row['rooms_from'] : null,
            'rooms_to'         => $row['rooms_to'] !== null ? (int) $row['rooms_to'] : null,
            'location_text'    => $row['location_text'] ?? '',
            'owner_user_id'    => (int) ($row['owner_user_id'] ?? 0),
            'owner_name'       => $row['owner_name'] ?? '',
            'created_at'       => $row['created_at'] ?? '',
            'updated_at'       => $row['updated_at'] ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /** @param string[] $allowed */
    private function sanitize_allowed(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    /** @return mixed */
    private function decode_json_field(string $json)
    {
        if ($json === '' || $json === 'null') {
            return null;
        }

        $decoded = json_decode($json, true);

        return $decoded ?? null;
    }

    private function resolve_user_role(WP_User $user): string
    {
        if (user_can($user, 'manage_options')) {
            return 'administrator';
        }

        $roles = is_array($user->roles) ? $user->roles : [];

        if (in_array(EstateOfficeCRM_Installer::ROLE_MANAGER, $roles, true)) {
            return 'manager';
        }

        if (in_array(EstateOfficeCRM_Installer::ROLE_AGENT, $roles, true)) {
            return 'agent';
        }

        return 'user';
    }

    /** @return array<string,mixed>|null */
    private function get_agent_profile(int $user_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT phone, email, city, postal_code, bio, photo_id, office_id
                 FROM {$this->tables['agent_profiles']}
                 WHERE user_id = %d LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        $photo_url = '';
        if (! empty($row['photo_id'])) {
            $url       = wp_get_attachment_image_url((int) $row['photo_id'], 'medium');
            $photo_url = is_string($url) ? $url : '';
        }

        $row['photo_url'] = $photo_url;
        unset($row['photo_id']);

        return $row;
    }
}
