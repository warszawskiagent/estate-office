<?php
/**
 * About page renderer.
 *
 * @package EstateOfficeCRM\Admin\Pages
 */

namespace EstateOfficeCRM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Render plugin about/roadmap page.
 */
class About_Page {
    /**
     * Render view.
     */
    public function render(): void {
        require __DIR__ . '/../views/about.php';
    }
}
