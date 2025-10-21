<?php
/** @var EstateOffice\Admin\Agents_Page $this */
$agents = $this->get_agents();
$editing = isset( $agent_data ) && $agent_data;
?>
<div class="wrap estate-office-agents">
    <h1><?php esc_html_e( 'Agenci', 'estate-office' ); ?></h1>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <div class="estate-office-card">
        <h2>
            <?php echo $editing ? esc_html__( 'Edytuj agenta', 'estate-office' ) : esc_html__( 'Dodaj nowego agenta', 'estate-office' ); ?>
        </h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agents' . ( $editing ? '&agent=' . (int) $agent_data->id : '' ) ) ); ?>">
            <?php wp_nonce_field( 'estate_office_agent_action', 'estate_office_agent_nonce' ); ?>
            <input type="hidden" name="agent_id" value="<?php echo $editing ? (int) $agent_data->id : 0; ?>" />

            <div class="estate-office-field-group">
                <label for="estate-office-first-name"><?php esc_html_e( 'Imię', 'estate-office' ); ?> *</label>
                <input type="text" id="estate-office-first-name" name="first_name" value="<?php echo $editing ? esc_attr( $agent_data->first_name ) : ''; ?>" required />
            </div>

            <div class="estate-office-field-group">
                <label for="estate-office-last-name"><?php esc_html_e( 'Nazwisko', 'estate-office' ); ?> *</label>
                <input type="text" id="estate-office-last-name" name="last_name" value="<?php echo $editing ? esc_attr( $agent_data->last_name ) : ''; ?>" required />
            </div>

            <div class="estate-office-field-group">
                <label for="estate-office-phone"><?php esc_html_e( 'Telefon', 'estate-office' ); ?></label>
                <input type="text" id="estate-office-phone" name="phone" value="<?php echo $editing ? esc_attr( $agent_data->phone ) : ''; ?>" />
            </div>

            <div class="estate-office-field-group">
                <label for="estate-office-email"><?php esc_html_e( 'E-mail', 'estate-office' ); ?></label>
                <input type="email" id="estate-office-email" name="email" value="<?php echo $editing ? esc_attr( $agent_data->email ) : ''; ?>" />
            </div>

            <div class="estate-office-field-group estate-office-media-field">
                <label><?php esc_html_e( 'Zdjęcie agenta', 'estate-office' ); ?></label>
                <div class="estate-office-media-preview">
                    <?php if ( $editing && ! empty( $agent_data->photo_id ) ) : ?>
                        <?php echo wp_get_attachment_image( (int) $agent_data->photo_id, 'thumbnail', false, array( 'id' => 'estate-office-agent-photo-preview' ) ); ?>
                    <?php else : ?>
                        <img id="estate-office-agent-photo-preview" src="" alt="" style="display:none;" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="photo_id" id="estate-office-agent-photo" value="<?php echo $editing ? (int) $agent_data->photo_id : 0; ?>" />
                <button type="button" class="button estate-office-select-media" data-target="estate-office-agent-photo"><?php esc_html_e( 'Wybierz zdjęcie', 'estate-office' ); ?></button>
                <button type="button" class="button button-secondary estate-office-remove-media" data-target="estate-office-agent-photo"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
            </div>

            <div class="estate-office-field-group">
                <label for="estate-office-biography"><?php esc_html_e( 'Biografia', 'estate-office' ); ?></label>
                <textarea id="estate-office-biography" name="biography" rows="5"><?php echo $editing ? esc_textarea( $agent_data->biography ) : ''; ?></textarea>
            </div>

            <p>
                <button type="submit" class="button button-primary">
                    <?php echo $editing ? esc_html__( 'Zapisz zmiany', 'estate-office' ) : esc_html__( 'Dodaj agenta', 'estate-office' ); ?>
                </button>
                <?php if ( $editing ) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agents' ) ); ?>"><?php esc_html_e( 'Anuluj', 'estate-office' ); ?></a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <div class="estate-office-card">
        <h2><?php esc_html_e( 'Lista agentów', 'estate-office' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Imię i nazwisko', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $agents ) ) : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'Brak agentów do wyświetlenia.', 'estate-office' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $agents as $agent ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( trim( $agent->first_name . ' ' . $agent->last_name ) ); ?>
                            </td>
                            <td><?php echo esc_html( $agent->phone ); ?></td>
                            <td>
                                <?php if ( $agent->email ) : ?>
                                    <a href="mailto:<?php echo esc_attr( $agent->email ); ?>"><?php echo esc_html( $agent->email ); ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agents&agent=' . (int) $agent->id ) ); ?>">
                                    <?php esc_html_e( 'Edytuj', 'estate-office' ); ?>
                                </a>
                                <a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=estate-office-agents&action=delete&agent=' . (int) $agent->id ), 'estate_office_delete_agent_' . (int) $agent->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć agenta?', 'estate-office' ) ); ?>');">
                                    <?php esc_html_e( 'Usuń', 'estate-office' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
