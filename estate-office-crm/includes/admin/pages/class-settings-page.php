<?php
/**
 * Settings page renderer.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Render plugin settings page.
 */
class Settings_Page {
    /**
     * Output settings screen.
     */
    public function render(): void {
        $options = get_option( 'estate_office_crm_settings', [] );
        require __DIR__ . '/../views/settings.php';
    }
}
