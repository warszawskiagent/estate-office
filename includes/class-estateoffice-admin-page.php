<?php
/**
 * Base class for CRM admin pages.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class EstateOffice_Admin_Page {

    /**
     * Page slug.
     *
     * @var string
     */
    protected $slug;

    /**
     * Parent slug.
     *
     * @var string
     */
    protected $parent_slug;

    /**
     * Menu title.
     *
     * @var string
     */
    protected $menu_title;

    /**
     * Page title.
     *
     * @var string
     */
    protected $page_title;

    /**
     * Capability required.
     *
     * @var string
     */
    protected $capability = ESTATE_OFFICE_MIN_CAPABILITY;

    /**
     * Icon for submenus.
     *
     * @var string|null
     */
    protected $icon = null;

    /**
     * Priority for menu.
     *
     * @var int
     */
    protected $position = null;

    /**
     * Constructor.
     */
    public function __construct( string $parent_slug = '' ) {
        $this->parent_slug = $parent_slug;
    }

    /**
     * Return slug.
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Register menu page.
     */
    public function register(): void {
        if ( empty( $this->parent_slug ) ) {
            add_menu_page(
                $this->page_title,
                $this->menu_title,
                $this->capability,
                $this->slug,
                [ $this, 'render' ],
                $this->icon,
                $this->position
            );
        } else {
            add_submenu_page(
                $this->parent_slug,
                $this->page_title,
                $this->menu_title,
                $this->capability,
                $this->slug,
                [ $this, 'render' ]
            );
        }
    }

    /**
     * Render page contents.
     */
    abstract public function render(): void;
}
