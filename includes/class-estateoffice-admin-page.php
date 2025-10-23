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
     * Retrieve required capability.
     */
    public function get_capability(): string {
        return $this->capability;
    }

    /**
     * Override capability requirement.
     */
    public function set_capability( string $capability ): void {
        $this->capability = $capability;
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

    /**
     * Output pagination controls for list screens.
     *
     * @param int   $total_items  Total number of records.
     * @param int   $per_page     Items displayed per page.
     * @param int   $current_page Current page number (1-indexed).
     * @param array $query_args   Additional query arguments to preserve.
     */
    protected function render_pagination( int $total_items, int $per_page, int $current_page, array $query_args = [] ): void {
        if ( $per_page <= 0 ) {
            return;
        }

        $total_pages = (int) ceil( $total_items / $per_page );
        if ( $total_pages <= 1 ) {
            return;
        }

        $query_args = array_filter(
            $query_args,
            static function ( $value ) {
                return '' !== $value && null !== $value;
            }
        );

        unset( $query_args['paged'] );

        $base = esc_url_raw( add_query_arg( 'paged', '%#%', admin_url( 'admin.php' ) ) );

        $links = paginate_links(
            [
                'base'      => $base,
                'format'    => '',
                'current'   => max( 1, $current_page ),
                'total'     => $total_pages,
                'type'      => 'array',
                'add_args'  => $query_args,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]
        );

        if ( empty( $links ) ) {
            return;
        }

        echo '<nav class="estate-office-pagination" aria-label="' . esc_attr__( 'Paginacja wyników', 'estate-office' ) . '">';
        echo '<ul class="estate-office-pagination__list">';

        foreach ( $links as $link ) {
            echo '<li class="estate-office-pagination__item">' . $link . '</li>';
        }

        echo '</ul>';
        echo '</nav>';
    }
}
