<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Settings\Manager;

/**
 * Settings page wrapper.
 */
class SettingsPage extends AbstractPage {
    private Manager $manager;

    public function __construct() {
        $this->manager = new Manager();
    }

    public function render(): void {
        $this->render_header(
            __( 'Ustawienia Estate Office', 'estate-office' ),
            __( 'Skonfiguruj integracje oraz strukturę formularzy.', 'estate-office' )
        );
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( Manager::OPTION_KEY );
            do_settings_sections( Manager::OPTION_KEY );
            submit_button( __( 'Zapisz ustawienia', 'estate-office' ) );
            ?>
        </form>
        <?php
        $this->render_footer();
    }
}
