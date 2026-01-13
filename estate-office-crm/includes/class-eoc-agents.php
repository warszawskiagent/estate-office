<?php

if (!defined('ABSPATH')) {
    exit;
}

class EOC_Agents {
    private const ACTION_SAVE = 'eoc_save_agent';

    public function register(): void {
        add_action('admin_post_' . self::ACTION_SAVE, array($this, 'handle_save'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $error = isset($_GET['eoc_error']) ? sanitize_text_field(wp_unslash($_GET['eoc_error'])) : '';
        $success = isset($_GET['eoc_success']) ? sanitize_text_field(wp_unslash($_GET['eoc_success'])) : '';

        $agents = get_users(array('role' => 'estate_agent'));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Agenci', 'estate-office-crm') . '</h1>';

        if ($error === 'invalid') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Uzupełnij poprawnie wymagane dane agenta.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'not_agent') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Wybrany użytkownik nie jest agentem.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'exists') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Użytkownik o podanym e-mailu lub loginie już istnieje.', 'estate-office-crm') . '</p></div>';
        } elseif ($error === 'db') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nie udało się zapisać danych agenta.', 'estate-office-crm') . '</p></div>';
        } elseif ($success === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Dane agenta zostały zapisane.', 'estate-office-crm') . '</p></div>';
        }

        echo '<h2>' . esc_html__('Lista agentów', 'estate-office-crm') . '</h2>';
        echo '<table class="widefat striped eoc-list-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Imię i nazwisko', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('E-mail', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Telefon', 'estate-office-crm') . '</th>';
        echo '<th>' . esc_html__('Akcje', 'estate-office-crm') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        if (empty($agents)) {
            echo '<tr><td colspan="4">' . esc_html__('Brak agentów do wyświetlenia.', 'estate-office-crm') . '</td></tr>';
        } else {
            foreach ($agents as $agent) {
                $phone = get_user_meta($agent->ID, 'eoc_agent_phone', true);
                $edit_link = add_query_arg(
                    array(
                        'page' => 'estate-office-crm-agents',
                        'agent_id' => $agent->ID,
                    ),
                    admin_url('admin.php')
                );
                echo '<tr>';
                echo '<td>' . esc_html($agent->display_name) . '</td>';
                echo '<td>' . esc_html($agent->user_email) . '</td>';
                echo '<td>' . esc_html($phone) . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url($edit_link) . '">' . esc_html__('Edytuj', 'estate-office-crm') . '</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        $selected_agent_id = isset($_GET['agent_id']) ? absint($_GET['agent_id']) : 0;
        $selected_agent = null;
        if ($selected_agent_id) {
            $selected_agent = get_user_by('id', $selected_agent_id);
            if (!$selected_agent || !in_array('estate_agent', (array) $selected_agent->roles, true)) {
                $selected_agent = null;
                $selected_agent_id = 0;
                if ($error === '') {
                    $error = 'not_agent';
                }
            }
        }
        $selected_phone = $selected_agent_id ? get_user_meta($selected_agent_id, 'eoc_agent_phone', true) : '';
        $selected_address = $selected_agent_id ? get_user_meta($selected_agent_id, 'eoc_agent_address', true) : '';
        $selected_bio = $selected_agent_id ? get_user_meta($selected_agent_id, 'eoc_agent_bio', true) : '';
        $selected_photo_id = $selected_agent_id ? absint(get_user_meta($selected_agent_id, 'eoc_agent_photo_id', true)) : 0;

        echo '<h2>' . esc_html__('Dodaj / edytuj agenta', 'estate-office-crm') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eoc_save_agent', 'eoc_agent_nonce');
        echo '<input type="hidden" name="action" value="eoc_save_agent" />';

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="eoc-agent-user">' . esc_html__('Wybierz istniejącego agenta', 'estate-office-crm') . '</label></th>';
        echo '<td><select id="eoc-agent-user" name="eoc_agent[user_id]">';
        echo '<option value="">' . esc_html__('Nowy agent', 'estate-office-crm') . '</option>';
        foreach ($agents as $agent) {
            $selected = $agent->ID === $selected_agent_id ? ' selected' : '';
            echo '<option value="' . esc_attr((string) $agent->ID) . '"' . $selected . '>' . esc_html($agent->display_name) . ' (' . esc_html($agent->user_email) . ')</option>';
        }
        echo '</select></td>';
        echo '</tr>';

        if (!$selected_agent) {
            echo '<tr>';
            echo '<th scope="row"><label for="eoc-agent-login">' . esc_html__('Login (dla nowego)', 'estate-office-crm') . '</label></th>';
            echo '<td><input type="text" id="eoc-agent-login" name="eoc_agent[user_login]" class="regular-text" /></td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-agent-email">' . esc_html__('E-mail', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="email" id="eoc-agent-email" name="eoc_agent[user_email]" class="regular-text" value="' . esc_attr($selected_agent ? $selected_agent->user_email : '') . '" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-agent-name">' . esc_html__('Imię i nazwisko', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-agent-name" name="eoc_agent[display_name]" class="regular-text" value="' . esc_attr($selected_agent ? $selected_agent->display_name : '') . '" /></td>';
        echo '</tr>';

        if (!$selected_agent) {
            echo '<tr>';
            echo '<th scope="row"><label for="eoc-agent-password">' . esc_html__('Hasło (dla nowego)', 'estate-office-crm') . '</label></th>';
            echo '<td><input type="password" id="eoc-agent-password" name="eoc_agent[user_password]" class="regular-text" /></td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Zdjęcie', 'estate-office-crm') . '</th>';
        echo '<td>';
        self::render_media_field('photo_id', $selected_photo_id);
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-agent-phone">' . esc_html__('Telefon', 'estate-office-crm') . '</label></th>';
        echo '<td><input type="text" id="eoc-agent-phone" name="eoc_agent[phone]" class="regular-text" value="' . esc_attr($selected_phone) . '" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-agent-address">' . esc_html__('Dane teleadresowe', 'estate-office-crm') . '</label></th>';
        echo '<td><textarea id="eoc-agent-address" name="eoc_agent[address]" class="large-text" rows="3">' . esc_textarea($selected_address) . '</textarea></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="eoc-agent-bio">' . esc_html__('Opis / Biografia', 'estate-office-crm') . '</label></th>';
        echo '<td><textarea id="eoc-agent-bio" name="eoc_agent[bio]" class="large-text" rows="4">' . esc_textarea($selected_bio) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';

        submit_button(__('Zapisz agenta', 'estate-office-crm'));
        echo '</form>';
        echo '</div>';
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'estate-office-crm'));
        }

        check_admin_referer('eoc_save_agent', 'eoc_agent_nonce');

        $data = isset($_POST['eoc_agent']) ? wp_unslash($_POST['eoc_agent']) : array();
        $user_id = isset($data['user_id']) ? absint($data['user_id']) : 0;
        $user_login = isset($data['user_login']) ? sanitize_user($data['user_login']) : '';
        $user_email = isset($data['user_email']) ? sanitize_email($data['user_email']) : '';
        $display_name = isset($data['display_name']) ? sanitize_text_field($data['display_name']) : '';
        $user_password = isset($data['user_password']) ? (string) $data['user_password'] : '';
        $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
        $address = isset($data['address']) ? sanitize_textarea_field($data['address']) : '';
        $bio = isset($data['bio']) ? sanitize_textarea_field($data['bio']) : '';
        $photo_id = isset($data['photo_id']) ? absint($data['photo_id']) : 0;

        if ($user_id) {
            if (!$user_email || !$display_name) {
                $this->redirect_with_error('invalid');
            }

            $user = get_user_by('id', $user_id);
            if (!$user || !in_array('estate_agent', (array) $user->roles, true)) {
                $this->redirect_with_error('not_agent');
            }

            $updated = wp_update_user(array(
                'ID' => $user_id,
                'user_email' => $user_email,
                'display_name' => $display_name,
            ));

            if (is_wp_error($updated)) {
                $this->redirect_with_error('db');
            }
        } else {
            if ($user_login === '' || $user_email === '' || $display_name === '' || $user_password === '') {
                $this->redirect_with_error('invalid');
            }

            if (username_exists($user_login) || email_exists($user_email)) {
                $this->redirect_with_error('exists');
            }

            $user_id = wp_create_user($user_login, $user_password, $user_email);
            if (is_wp_error($user_id)) {
                $this->redirect_with_error('db');
            }

            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name,
                'role' => 'estate_agent',
            ));
        }

        update_user_meta($user_id, 'eoc_agent_phone', $phone);
        update_user_meta($user_id, 'eoc_agent_address', $address);
        update_user_meta($user_id, 'eoc_agent_bio', $bio);
        update_user_meta($user_id, 'eoc_agent_photo_id', $photo_id);

        $this->redirect_with_success();
    }

    private static function render_media_field(string $field_key, int $attachment_id = 0): void {
        $input_name = 'eoc_agent[' . $field_key . ']';
        $preview = '';
        if ($attachment_id) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            if ($image_url) {
                $preview = '<img src="' . esc_url($image_url) . '" alt="" class="eoc-media-preview" />';
            }
        }

        echo '<div class="eoc-media-field" data-target="' . esc_attr($field_key) . '">';
        echo '<input type="hidden" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $attachment_id) . '" />';
        echo '<div class="eoc-media-preview-wrapper">' . $preview . '</div>';
        echo '<button type="button" class="button eoc-media-select">' . esc_html__('Wybierz zdjęcie', 'estate-office-crm') . '</button>';
        echo '<button type="button" class="button eoc-media-remove">' . esc_html__('Usuń', 'estate-office-crm') . '</button>';
        echo '</div>';
    }

    private function redirect_with_error(string $code): void {
        wp_safe_redirect(add_query_arg('eoc_error', $code, admin_url('admin.php?page=estate-office-crm-agents')));
        exit;
    }

    private function redirect_with_success(): void {
        wp_safe_redirect(add_query_arg('eoc_success', '1', admin_url('admin.php?page=estate-office-crm-agents')));
        exit;
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'estate-office-crm_page_estate-office-crm-agents') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'eoc-agents',
            EOC_PLUGIN_URL . 'assets/admin/agents.js',
            array('jquery'),
            EOC_PLUGIN_VERSION,
            true
        );
        wp_enqueue_style(
            'eoc-admin',
            EOC_PLUGIN_URL . 'assets/admin/admin.css',
            array(),
            EOC_PLUGIN_VERSION
        );
    }
}
