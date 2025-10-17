<?php
/**
 * Ekran administracyjny zarządzania nieruchomościami.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ESTATE_OFFICE_PATH . 'includes/admin/class-estate-office-admin-agent-assignment.php';
require_once ESTATE_OFFICE_PATH . 'includes/properties/class-estate-office-property-watermark-service.php';

/**
 * Class Estate_Office_Admin_Properties_Page
 */
class Estate_Office_Admin_Properties_Page {

    use Estate_Office_Admin_Agent_Assignment;

    private const PAGE_SLUG = 'estate-office-properties';

    /**
     * Repozytorium nieruchomości.
     *
     * @var Estate_Office_Property_Repository
     */
    private Estate_Office_Property_Repository $repository;

    /**
     * Repozytorium umów.
     *
     * @var Estate_Office_Contract_Repository
     */
    private Estate_Office_Contract_Repository $contracts_repository;

    /**
     * Buforowany klucz API Map Google.
     *
     * @var string|null
     */
    private ?string $google_maps_api_key = null;

    /**
     * Serwis znakowania zdjęć znakiem wodnym.
     *
     * @var Estate_Office_Property_Watermark_Service
     */
    private Estate_Office_Property_Watermark_Service $watermark_service;

    /**
     * Konstruktor.
     *
     * @param Estate_Office_Property_Repository|null         $repository           Repozytorium nieruchomości.
     * @param Estate_Office_Contract_Repository|null         $contracts_repository Repozytorium umów.
     * @param Estate_Office_Agent_Repository|null            $agent_repository     Repozytorium agentów.
     * @param Estate_Office_Property_Watermark_Service|null  $watermark_service    Serwis znakowania zdjęć.
     */
    public function __construct(
        ?Estate_Office_Property_Repository $repository = null,
        ?Estate_Office_Contract_Repository $contracts_repository = null,
        ?Estate_Office_Agent_Repository $agent_repository = null,
        ?Estate_Office_Property_Watermark_Service $watermark_service = null
    ) {
        $this->repository            = $repository ?? new Estate_Office_Property_Repository();
        $this->contracts_repository  = $contracts_repository ?? new Estate_Office_Contract_Repository();
        $this->init_agent_repository( $agent_repository );
        $this->watermark_service     = $watermark_service ?? new Estate_Office_Property_Watermark_Service();
    }

    /**
     * Rejestracja hooków.
     *
     * @return void
     */
    public function hooks() : void {
        add_action( 'admin_post_estate_office_save_property', [ $this, 'handle_save_property' ] );
        add_action( 'admin_post_estate_office_delete_property', [ $this, 'handle_delete_property' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this->repository, 'expire_new_offer_flags' ] );
    }

    /**
     * Dołącza zasoby CSS/JS.
     *
     * @param string $hook Hook bieżącej strony.
     *
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        if ( 'estate-office-crm_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_media();

        $handle = 'estate-office-admin-properties';
        wp_register_style( $handle, false, [ 'estate-office-admin-forms' ], ESTATE_OFFICE_VERSION );
        wp_enqueue_style( $handle );

        $deps    = [ 'jquery' ];
        $api_key = $this->get_google_maps_api_key();
        if ( '' !== $api_key ) {
            $google_handle = 'estate-office-google-maps';
            wp_enqueue_script(
                $google_handle,
                add_query_arg(
                    [
                        'key'       => $api_key,
                        'libraries' => 'places',
                    ],
                    'https://maps.googleapis.com/maps/api/js'
                ),
                [],
                null,
                true
            );
            $deps[] = $google_handle;
        }

        wp_register_script( $handle, false, $deps, ESTATE_OFFICE_VERSION, true );
        wp_enqueue_script( $handle );

        wp_localize_script(
            $handle,
            'EstateOfficePropertyMap',
            [
                'hasMap'     => '' !== $api_key,
                'defaultLat' => 52.2296756,
                'defaultLng' => 21.0122287,
                'locale'     => get_locale(),
            ]
        );

        wp_add_inline_script(
            $handle,
            <<<'JS'
jQuery(function($){
    const typeField = $("select[name=\"property_type\"]");
    function toggleTypeSections(){
        const value = typeField.val();
        $("[data-property-type]").each(function(){
            const allowed = ($(this).data("property-type") || "").toString().split(',');
            $(this).toggle(allowed.includes(value));
        });
    }
    typeField.on('change', toggleTypeSections);
    toggleTypeSections();

    const galleryButton = $('#estate-office-select-gallery');
    const galleryField = $('#estate-office-gallery');
    const galleryPreview = $('#estate-office-gallery-preview');
    const escapeHtml = function(string){
        return String(string || '').replace(/[&<>"']/g, function(char){
            const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return entities[char] || char;
        });
    };
    function renderGalleryPreview(items){
        if (!galleryPreview.length){
            return;
        }
        if (!items.length){
            const emptyMessage = escapeHtml(galleryPreview.data('empty') || '');
            galleryPreview.addClass('is-empty').html(emptyMessage ? '<p class="description">' + emptyMessage + '</p>' : '');
            return;
        }
        let html = '';
        items.forEach(function(item){
            html += '<figure><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.alt) + '" /><figcaption>' + escapeHtml(item.title) + '</figcaption></figure>';
        });
        galleryPreview.removeClass('is-empty').html(html);
    }
    if ( galleryButton.length ) {
        galleryButton.on('click', function(event){
            event.preventDefault();
            const frame = wp.media({
                title: galleryButton.data('title'),
                multiple: true,
                library: { type: 'image' }
            });
            frame.on('select', function(){
                const selection = frame.state().get('selection');
                const ids = selection.map(function(attachment){
                    return attachment.id;
                }).toArray();
                galleryField.val(ids.join(','));
                const items = [];
                selection.each(function(attachment){
                    const data = attachment.toJSON();
                    const thumb = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : (data.icon || data.url || '');
                    items.push({
                        url: thumb,
                        title: data.title || ('ID ' + data.id),
                        alt: data.alt || data.title || ''
                    });
                });
                renderGalleryPreview(items);
            });
            frame.open();
        });
    }

    galleryField.on('change', function(){
        if ( ! $(this).val() ) {
            renderGalleryPreview([]);
        }
    });

    const plan2dButton = $('#estate-office-select-plan-2d');
    const plan2dField = $('#estate-office-plan-2d');
    if ( plan2dButton.length ) {
        plan2dButton.on('click', function(event){
            event.preventDefault();
            const frame = wp.media({ title: plan2dButton.data('title'), multiple: false });
            frame.on('select', function(){
                const attachment = frame.state().get('selection').first();
                plan2dField.val(attachment ? attachment.id : '');
            });
            frame.open();
        });
    }

    const plan3dButton = $('#estate-office-select-plan-3d');
    const plan3dField = $('#estate-office-plan-3d');
    if ( plan3dButton.length ) {
        plan3dButton.on('click', function(event){
            event.preventDefault();
            const frame = wp.media({ title: plan3dButton.data('title'), multiple: false });
            frame.on('select', function(){
                const attachment = frame.state().get('selection').first();
                plan3dField.val(attachment ? attachment.id : '');
            });
            frame.open();
        });
    }

    const landRegisterCheckbox = $('#land_register_missing');
    const landRegisterField = $('#land_register_number');
    function toggleLandRegister(){
        const disabled = landRegisterCheckbox.is(':checked');
        landRegisterField.prop('disabled', disabled);
        if ( disabled ) {
            landRegisterField.val('');
        }
    }
    landRegisterCheckbox.on('change', toggleLandRegister);
    toggleLandRegister();

    const priceField = $('input[name="price"]');
    const areaField = $('input[name="area_total"]');
    const currencyField = $('select[name="price_currency"]');
    const pricePerField = $('input[data-price-per-sqm="display"]');
    function updatePricePer(){
        if (!pricePerField.length){
            return;
        }
        const price = parseFloat((priceField.val() || '').toString().replace(',', '.'));
        const area = parseFloat((areaField.val() || '').toString().replace(',', '.'));
        if (price > 0 && area > 0){
            const computed = price / Math.max(area, 0.01);
            const formatter = new Intl.NumberFormat(EstateOfficePropertyMap.locale || 'pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            pricePerField.val(formatter.format(computed) + ' ' + (currencyField.val() || ''));
        } else {
            pricePerField.val('');
        }
    }
    priceField.on('input change', updatePricePer);
    areaField.on('input change', updatePricePer);
    currencyField.on('change', updatePricePer);
    updatePricePer();

    const ownershipSelect = $('#ownership_status');
    const ownershipCustom = $('#ownership_status_custom').closest('.field');
    function toggleOwnershipCustom(){
        if (!ownershipSelect.length || !ownershipCustom.length){
            return;
        }
        const show = ownershipSelect.val() === 'INNE';
        ownershipCustom.toggleClass('is-hidden', ! show);
        if ( ! show ) {
            $('#ownership_status_custom').val('');
        }
    }
    if ( ownershipSelect.length && ownershipCustom.length ) {
        ownershipSelect.on('change', toggleOwnershipCustom);
        toggleOwnershipCustom();
    }

    function initMap(){
        const mapContainer = document.getElementById('estate-office-property-map');
        if (!mapContainer || !EstateOfficePropertyMap.hasMap || typeof google === 'undefined' || !google.maps){
            return;
        }
        const latField = $('input[name="latitude"]');
        const lngField = $('input[name="longitude"]');
        const initialLat = parseFloat(mapContainer.dataset.lat) || EstateOfficePropertyMap.defaultLat;
        const initialLng = parseFloat(mapContainer.dataset.lng) || EstateOfficePropertyMap.defaultLng;
        const zoom = parseInt(mapContainer.dataset.zoom || '14', 10);
        const map = new google.maps.Map(mapContainer, {
            center: { lat: initialLat, lng: initialLng },
            zoom: zoom
        });
        let marker = new google.maps.Marker({
            position: { lat: initialLat, lng: initialLng },
            map: map,
            draggable: true
        });
        const updateFields = function(lat, lng){
            latField.val(lat.toFixed(6));
            lngField.val(lng.toFixed(6));
        };
        marker.addListener('dragend', function(event){
            updateFields(event.latLng.lat(), event.latLng.lng());
        });
        map.addListener('click', function(event){
            marker.setPosition(event.latLng);
            updateFields(event.latLng.lat(), event.latLng.lng());
        });
    }
    if ( document.readyState === 'complete' ) {
        initMap();
    } else {
        $(window).on('load', initMap);
    }
});
JS
        );
    }

    /**
     * Renderuje ekran.
     *
     * @return void
     */
    public function render_page() : void {
        if ( ! current_user_can( 'manage_estate_office_properties' ) ) {
            wp_die( esc_html__( 'Nie masz uprawnień do przeglądania nieruchomości.', 'estate-office' ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        $id     = isset( $_GET['property_id'] ) ? absint( $_GET['property_id'] ) : 0;

        if ( 'new' === $action ) {
            $this->render_form();

            return;
        }

        if ( 'edit' === $action && $id ) {
            $property = $this->repository->find( $id );
            if ( null === $property ) {
                $this->render_list( __( 'Nie znaleziono wskazanej nieruchomości.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_form( $property );

            return;
        }

        if ( 'view' === $action && $id ) {
            $property = $this->repository->find( $id );
            if ( null === $property ) {
                $this->render_list( __( 'Nie znaleziono wskazanej nieruchomości.', 'estate-office' ), 'error' );

                return;
            }

            $this->render_profile( $property );

            return;
        }

        $this->render_list();
    }

    /**
     * Wyświetla listę nieruchomości.
     *
     * @param string $notice    Opcjonalny komunikat.
     * @param string $notice_id Typ komunikatu.
     *
     * @return void
     */
    private function render_list( string $notice = '', string $notice_id = 'success' ) : void {
        $search           = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $transaction_type = isset( $_GET['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_type'] ) ) : '';
        $property_type    = isset( $_GET['property_type'] ) ? sanitize_text_field( wp_unslash( $_GET['property_type'] ) ) : '';
        $paged            = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $query = $this->repository->paginate(
            [
                'paged'            => $paged,
                'search'           => $search,
                'transaction_type' => $transaction_type,
                'property_type'    => $property_type,
            ]
        );

        $agent_ids = [];
        if ( isset( $query['items'] ) && is_array( $query['items'] ) ) {
            $agent_ids = array_map( 'intval', wp_list_pluck( $query['items'], 'agent_id' ) );
        }
        $this->prime_agent_labels( $agent_ids );

        $message       = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $message_class = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';

        echo '<div class="wrap estate-office-properties">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Nieruchomości', 'estate-office' ) . '</h1>';
        printf(
            ' <a href="%s" class="page-title-action">%s</a>',
            esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=new' ) ),
            esc_html__( 'Dodaj nową nieruchomość', 'estate-office' )
        );
        echo '<hr class="wp-header-end" />';

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $message_class ), esc_html( $message ) );
        }

        if ( $notice ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $notice_id ), esc_html( $notice ) );
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        printf(
            '<label class="screen-reader-text" for="estate-office-search">%s</label>',
            esc_html__( 'Szukaj nieruchomości', 'estate-office' )
        );
        printf(
            '<input type="search" id="estate-office-search" name="s" value="%s" placeholder="%s" />',
            esc_attr( $search ),
            esc_attr__( 'Szukaj po numerze, adresie lub tytule…', 'estate-office' )
        );

        echo '<select name="transaction_type">';
        echo '<option value="">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</option>';
        foreach ( $this->get_transaction_types() as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $transaction_type, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        echo '<select name="property_type">';
        echo '<option value="">' . esc_html__( 'Rodzaj nieruchomości', 'estate-office' ) . '</option>';
        foreach ( $this->get_property_types() as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $property_type, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        submit_button( __( 'Filtruj', 'estate-office' ), 'secondary', '', false );
        echo '</div>';
        echo '<div class="tablenav-pages">';

        $pagination = paginate_links(
            [
                'base'      => add_query_arg( [ 'paged' => '%#%' ] ),
                'format'    => '',
                'prev_text' => __( '&laquo;', 'estate-office' ),
                'next_text' => __( '&raquo;', 'estate-office' ),
                'total'     => max( 1, (int) $query['total_page'] ),
                'current'   => max( 1, $paged ),
            ]
        );

        if ( $pagination ) {
            echo wp_kses_post( $pagination );
        }

        echo '</div>';
        echo '</div>';
        echo '</form>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        $columns = [
            'listing_number' => __( 'Numer oferty', 'estate-office' ),
            'title'          => __( 'Tytuł', 'estate-office' ),
            'address'        => __( 'Adres', 'estate-office' ),
            'price'          => __( 'Cena', 'estate-office' ),
            'price_sqm'      => __( 'Cena za m²', 'estate-office' ),
            'area'           => __( 'Metraż', 'estate-office' ),
            'rooms'          => __( 'Liczba pokoi', 'estate-office' ),
            'guardian'       => __( 'Opiekun', 'estate-office' ),
            'updated'        => __( 'Aktualizacja', 'estate-office' ),
        ];

        foreach ( $columns as $key => $label ) {
            printf( '<th scope="col" class="column-%1$s">%2$s</th>', esc_attr( $key ), esc_html( $label ) );
        }
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $query['items'] ) ) {
            echo '<tr><td colspan="9">' . esc_html__( 'Brak nieruchomości spełniających kryteria.', 'estate-office' ) . '</td></tr>';
        } else {
            foreach ( $query['items'] as $item ) {
                $price_display     = $item['price'] ? number_format_i18n( (float) $item['price'], 2 ) . ' ' . esc_html( $item['price_currency'] ) : '&mdash;';
                $price_sqm_display = $item['price_per_sqm'] ? number_format_i18n( (float) $item['price_per_sqm'], 2 ) . ' ' . esc_html( $item['price_currency'] ) : '&mdash;';
                $area_display      = $item['area_total'] ? number_format_i18n( (float) $item['area_total'], 2 ) . ' m²' : '&mdash;';
                $rooms_display     = $item['rooms'] ? (int) $item['rooms'] : '&mdash;';
                $address_line      = trim( $item['street'] . ' ' . $item['street_number'] );
                $address_display   = $address_line ? $address_line . ', ' . $item['city'] : $item['city'];
                $updated           = $item['updated_at'] ?: $item['created_at'];

                echo '<tr>';
                $view_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=view&property_id=' . absint( $item['id'] ) );
                $edit_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=edit&property_id=' . absint( $item['id'] ) );

                echo '<td><strong><a href="' . esc_url( $view_url ) . '">' . esc_html( $item['listing_number'] ) . '</a></strong>';

                $row_actions = [];
                $row_actions['view'] = '<span class="view"><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'Podgląd', 'estate-office' ) . '</a></span>';

                if ( current_user_can( 'edit_estate_office_properties' ) ) {
                    $row_actions['edit'] = '<span class="edit"><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a></span>';
                }

                if ( current_user_can( 'delete_estate_office_properties' ) ) {
                    $delete_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=estate_office_delete_property&property_id=' . absint( $item['id'] ) ),
                        'estate_office_delete_property_' . absint( $item['id'] )
                    );
                    $row_actions['delete'] = '<span class="trash"><a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Usuń', 'estate-office' ) . '</a></span>';
                }

                if ( ! empty( $row_actions ) ) {
                    echo '<div class="row-actions">' . implode( ' | ', array_map( 'wp_kses_post', $row_actions ) ) . '</div>';
                }

                echo '</td>';
                printf( '<td>%s</td>', esc_html( $item['title'] ) );
                printf( '<td>%s</td>', esc_html( $address_display ?: __( 'Brak danych adresowych', 'estate-office' ) ) );
                printf( '<td>%s</td>', wp_kses_post( $price_display ) );
                printf( '<td>%s</td>', wp_kses_post( $price_sqm_display ) );
                printf( '<td>%s</td>', wp_kses_post( $area_display ) );
                printf( '<td>%s</td>', esc_html( $rooms_display ) );
                $guardian_display = $this->format_agent_cell( (int) ( $item['agent_id'] ?? 0 ) );
                printf( '<td>%s</td>', wp_kses_post( $guardian_display ) );
                printf( '<td>%s</td>', esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated ) ) );
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Wyświetla profil nieruchomości.
     *
     * @param array<string,mixed> $property Dane nieruchomości.
     *
     * @return void
     */
    private function render_profile( array $property ) : void {
        $prepared    = $this->prepare_property_for_form( $property );
        $property_id = (int) ( $property['id'] ?? 0 );
        $agent_id    = (int) ( $property['agent_id'] ?? 0 );

        if ( $agent_id > 0 ) {
            $this->prime_agent_labels( [ $agent_id ] );
        }

        $contract    = null;
        $contract_id = (int) ( $property['contract_id'] ?? 0 );
        $clients     = [];

        if ( $contract_id > 0 ) {
            $contract = $this->contracts_repository->find( $contract_id );
            if ( $contract ) {
                $clients = $this->contracts_repository->get_clients( $contract_id );
            }
        }

        $message = isset( $_GET['estate-office-message'] ) ? sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) ) : '';
        $status  = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'success';

        $transaction_label = $this->get_transaction_types()[ $prepared['transaction_type'] ] ?? $prepared['transaction_type'];
        $property_label    = $this->get_property_types()[ $prepared['property_type'] ] ?? $prepared['property_type'];

        $house_type_label = '';
        if ( 'DOM' === $prepared['property_type'] && ! empty( $property['house_type'] ) ) {
            $house_type_label = $this->get_house_types()[ $property['house_type'] ] ?? $property['house_type'];
        }

        $ownership_raw   = (string) ( $property['ownership_status'] ?? '' );
        $ownership_label = '';
        if ( '' !== $ownership_raw ) {
            $ownership_options = $this->get_ownership_status_options();
            $ownership_label   = $ownership_options[ $ownership_raw ] ?? $ownership_raw;
        }

        $currency = $property['price_currency'] ?? 'PLN';

        $price_display = '';
        if ( isset( $property['price'] ) && '' !== $property['price'] && null !== $property['price'] ) {
            $price_display = number_format_i18n( (float) $property['price'], 2 ) . ' ' . esc_html( $currency );
        }

        $price_sqm_display = '';
        if ( isset( $property['price_per_sqm'] ) && '' !== $property['price_per_sqm'] && null !== $property['price_per_sqm'] ) {
            $price_sqm_display = number_format_i18n( (float) $property['price_per_sqm'], 2 ) . ' ' . esc_html( $currency );
        } elseif ( ! empty( $prepared['price_per_sqm_display'] ) ) {
            $price_sqm_display = $prepared['price_per_sqm_display'];
        }

        $rent_display = '';
        if ( isset( $property['administrative_rent'] ) && '' !== $property['administrative_rent'] && null !== $property['administrative_rent'] ) {
            $rent_display = number_format_i18n( (float) $property['administrative_rent'], 2 ) . ' ' . esc_html( $currency );
        }

        $area_total_display = '';
        if ( isset( $property['area_total'] ) && '' !== $property['area_total'] && null !== $property['area_total'] ) {
            $area_total_display = number_format_i18n( (float) $property['area_total'], 2 ) . ' m²';
        }

        $area_plot_display = '';
        if ( isset( $property['area_plot'] ) && '' !== $property['area_plot'] && null !== $property['area_plot'] ) {
            $area_plot_display = number_format_i18n( (float) $property['area_plot'], 2 ) . ' m²';
        }

        $plot_shape_label = '';
        if ( ! empty( $property['plot_shape'] ) ) {
            $plot_shape_label = $this->get_plot_shapes()[ $property['plot_shape'] ] ?? $property['plot_shape'];
        }

        $building_details = is_array( $prepared['building_details'] ) ? $prepared['building_details'] : [];
        $media_details    = is_array( $prepared['media'] ) ? $prepared['media'] : [];
        $amenities        = is_array( $prepared['amenities'] ) ? $prepared['amenities'] : [];
        $equipment        = is_array( $prepared['equipment'] ) ? $prepared['equipment'] : [];
        $additional_areas = is_array( $prepared['additional_areas'] ) ? $prepared['additional_areas'] : [];

        echo '<div class="wrap estate-office-property-profile">';
        echo '<h1>' . esc_html( $prepared['title'] ?: __( 'Nieruchomość', 'estate-office' ) ) . '</h1>';
        if ( ! empty( $prepared['listing_number'] ) ) {
            echo '<p class="description">' . esc_html__( 'Numer oferty:', 'estate-office' ) . ' ' . esc_html( $prepared['listing_number'] ) . '</p>';
        }

        if ( $message ) {
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<div class="estate-office-profile-columns" style="display:flex;gap:2rem;flex-wrap:wrap;">';

        echo '<div style="flex:1 1 360px;min-width:320px;">';
        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Dane podstawowe', 'estate-office' ) . '</h2>';

        $basic_rows = [];
        $basic_rows[] = [ 'label' => __( 'Typ transakcji', 'estate-office' ), 'value' => $transaction_label, 'html' => false ];
        $basic_rows[] = [ 'label' => __( 'Rodzaj nieruchomości', 'estate-office' ), 'value' => $property_label, 'html' => false ];
        if ( $house_type_label ) {
            $basic_rows[] = [ 'label' => __( 'Typ domu', 'estate-office' ), 'value' => $house_type_label, 'html' => false ];
        }
        if ( $ownership_label ) {
            $basic_rows[] = [ 'label' => __( 'Stan prawny', 'estate-office' ), 'value' => $ownership_label, 'html' => false ];
        }
        if ( ! empty( $property['land_register_number'] ) ) {
            $basic_rows[] = [ 'label' => __( 'Numer księgi wieczystej', 'estate-office' ), 'value' => $property['land_register_number'], 'html' => false ];
        } else {
            $basic_rows[] = [ 'label' => __( 'Księga wieczysta', 'estate-office' ), 'value' => __( 'Brak informacji', 'estate-office' ), 'html' => false ];
        }
        if ( $plot_shape_label ) {
            $basic_rows[] = [ 'label' => __( 'Kształt działki', 'estate-office' ), 'value' => $plot_shape_label, 'html' => false ];
        }
        if ( $agent_id > 0 ) {
            $basic_rows[] = [ 'label' => __( 'Opiekun', 'estate-office' ), 'value' => $this->format_agent_cell( $agent_id ), 'html' => true ];
        }
        if ( $contract ) {
            $contract_link = add_query_arg(
                [
                    'page'        => 'estate-office-contracts',
                    'action'      => 'view',
                    'contract_id' => (int) $contract['id'],
                ],
                admin_url( 'admin.php' )
            );
            $basic_rows[] = [
                'label' => __( 'Powiązana umowa', 'estate-office' ),
                'value' => '<a href="' . esc_url( $contract_link ) . '">' . esc_html( $contract['contract_number'] ?? '' ) . '</a>',
                'html'  => true,
            ];
        }

        $custom_values = [];
        if ( isset( $prepared['custom_fields'] ) && is_array( $prepared['custom_fields'] ) ) {
            $custom_values = $prepared['custom_fields'];
        }

        foreach ( Estate_Office_Dynamic_Fields::format_for_display( 'property', $custom_values ) as $label => $value ) {
            $basic_rows[] = [ 'label' => $label, 'value' => $value, 'html' => false ];
        }

        echo '<table class="widefat fixed striped">';
        foreach ( $basic_rows as $row ) {
            $value = (string) ( $row['value'] ?? '' );
            if ( '' === trim( $value ) ) {
                continue;
            }
            echo '<tr><th style="width:35%;">' . esc_html( (string) $row['label'] ) . '</th><td>';
            if ( ! empty( $row['html'] ) ) {
                echo wp_kses_post( $value );
            } else {
                echo esc_html( $value );
            }
            echo '</td></tr>';
        }
        echo '</table>';
        echo '</section>';

        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Parametry', 'estate-office' ) . '</h2>';
        $parameter_rows = [];
        if ( $price_display ) {
            $parameter_rows[] = [ 'label' => __( 'Cena', 'estate-office' ), 'value' => $price_display, 'html' => true ];
        }
        if ( $price_sqm_display ) {
            $parameter_rows[] = [ 'label' => __( 'Cena za m²', 'estate-office' ), 'value' => $price_sqm_display, 'html' => true ];
        }
        if ( $rent_display ) {
            $parameter_rows[] = [ 'label' => __( 'Czynsz administracyjny', 'estate-office' ), 'value' => $rent_display, 'html' => true ];
        }
        if ( $area_total_display ) {
            $parameter_rows[] = [ 'label' => __( 'Metraż', 'estate-office' ), 'value' => $area_total_display, 'html' => true ];
        }
        if ( $area_plot_display ) {
            $parameter_rows[] = [ 'label' => __( 'Powierzchnia działki', 'estate-office' ), 'value' => $area_plot_display, 'html' => true ];
        }
        if ( isset( $property['rooms'] ) && '' !== $property['rooms'] && null !== $property['rooms'] ) {
            $parameter_rows[] = [ 'label' => __( 'Liczba pokoi', 'estate-office' ), 'value' => (string) (int) $property['rooms'], 'html' => false ];
        }
        if ( isset( $property['bedrooms'] ) && '' !== $property['bedrooms'] && null !== $property['bedrooms'] ) {
            $parameter_rows[] = [ 'label' => __( 'Liczba sypialni', 'estate-office' ), 'value' => (string) (int) $property['bedrooms'], 'html' => false ];
        }
        if ( isset( $property['bathrooms'] ) && '' !== $property['bathrooms'] && null !== $property['bathrooms'] ) {
            $parameter_rows[] = [ 'label' => __( 'Liczba łazienek', 'estate-office' ), 'value' => (string) (int) $property['bathrooms'], 'html' => false ];
        }
        if ( isset( $property['toilets'] ) && '' !== $property['toilets'] && null !== $property['toilets'] ) {
            $parameter_rows[] = [ 'label' => __( 'Liczba toalet', 'estate-office' ), 'value' => (string) (int) $property['toilets'], 'html' => false ];
        }
        if ( isset( $property['year_built'] ) && '' !== $property['year_built'] && null !== $property['year_built'] ) {
            $parameter_rows[] = [ 'label' => __( 'Rok budowy', 'estate-office' ), 'value' => (string) (int) $property['year_built'], 'html' => false ];
        }
        if ( isset( $property['floor'] ) && '' !== $property['floor'] && null !== $property['floor'] ) {
            $parameter_rows[] = [ 'label' => __( 'Piętro', 'estate-office' ), 'value' => (string) (int) $property['floor'], 'html' => false ];
        }
        if ( isset( $property['total_floors'] ) && '' !== $property['total_floors'] && null !== $property['total_floors'] ) {
            $parameter_rows[] = [ 'label' => __( 'Liczba pięter', 'estate-office' ), 'value' => (string) (int) $property['total_floors'], 'html' => false ];
        }

        if ( empty( $parameter_rows ) ) {
            echo '<p>' . esc_html__( 'Brak zapisanych parametrów liczbowych.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            foreach ( $parameter_rows as $row ) {
                echo '<tr><th style="width:35%;">' . esc_html( (string) $row['label'] ) . '</th><td>';
                if ( ! empty( $row['html'] ) ) {
                    echo wp_kses_post( (string) $row['value'] );
                } else {
                    echo esc_html( (string) $row['value'] );
                }
                echo '</td></tr>';
            }
            echo '</table>';
        }
        echo '</section>';

        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Dane adresowe', 'estate-office' ) . '</h2>';
        $address_rows = [];

        $street_line = trim( ( $prepared['street'] ?? '' ) . ' ' . ( $prepared['street_number'] ?? '' ) );
        if ( ! empty( $prepared['apartment_number'] ) ) {
            $street_line = trim( $street_line . '/' . $prepared['apartment_number'] );
        }
        if ( $street_line ) {
            $address_rows[] = [ 'label' => __( 'Ulica', 'estate-office' ), 'value' => $street_line, 'html' => false ];
        }
        if ( ! empty( $prepared['district'] ) ) {
            $address_rows[] = [ 'label' => __( 'Dzielnica', 'estate-office' ), 'value' => $prepared['district'], 'html' => false ];
        }
        if ( ! empty( $prepared['city'] ) ) {
            $city_line = $prepared['postal_code'] ? $prepared['postal_code'] . ' ' . $prepared['city'] : $prepared['city'];
            $address_rows[] = [ 'label' => __( 'Miasto', 'estate-office' ), 'value' => $city_line, 'html' => false ];
        }
        if ( ! empty( $prepared['voivodeship'] ) ) {
            $address_rows[] = [ 'label' => __( 'Województwo', 'estate-office' ), 'value' => $prepared['voivodeship'], 'html' => false ];
        }
        if ( ! empty( $prepared['county'] ) ) {
            $address_rows[] = [ 'label' => __( 'Powiat', 'estate-office' ), 'value' => $prepared['county'], 'html' => false ];
        }
        if ( ! empty( $prepared['precinct'] ) ) {
            $address_rows[] = [ 'label' => __( 'Obręb', 'estate-office' ), 'value' => $prepared['precinct'], 'html' => false ];
        }
        if ( ! empty( $prepared['plot_number'] ) ) {
            $address_rows[] = [ 'label' => __( 'Numer działki', 'estate-office' ), 'value' => $prepared['plot_number'], 'html' => false ];
        }

        if ( ! empty( $prepared['google_place_id'] ) ) {
            $address_rows[] = [ 'label' => __( 'Google Place ID', 'estate-office' ), 'value' => $prepared['google_place_id'], 'html' => false ];
        }
        if ( '' !== (string) ( $prepared['latitude'] ?? '' ) && '' !== (string) ( $prepared['longitude'] ?? '' ) ) {
            $coords = $prepared['latitude'] . ', ' . $prepared['longitude'];
            $map_url = add_query_arg(
                [
                    'api'   => '1',
                    'query' => $prepared['latitude'] . ',' . $prepared['longitude'],
                ],
                'https://www.google.com/maps/search/'
            );
            $address_rows[] = [
                'label' => __( 'Współrzędne', 'estate-office' ),
                'value' => '<a href="' . esc_url( $map_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $coords ) . '</a>',
                'html'  => true,
            ];
        }

        if ( empty( $address_rows ) ) {
            echo '<p>' . esc_html__( 'Brak danych adresowych.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            foreach ( $address_rows as $row ) {
                echo '<tr><th style="width:35%;">' . esc_html( (string) $row['label'] ) . '</th><td>';
                if ( ! empty( $row['html'] ) ) {
                    echo wp_kses_post( (string) $row['value'] );
                } else {
                    echo esc_html( (string) $row['value'] );
                }
                echo '</td></tr>';
            }
            echo '</table>';
        }
        echo '</section>';

        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Charakterystyka budynku', 'estate-office' ) . '</h2>';
        $building_rows = [];
        if ( ! empty( $building_details['finish_state'] ) ) {
            $building_rows[] = [
                'label' => __( 'Stan wykończenia', 'estate-office' ),
                'value' => $this->get_finish_states()[ $building_details['finish_state'] ] ?? $building_details['finish_state'],
                'html'  => false,
            ];
        }
        if ( ! empty( $building_details['exposure'] ) && is_array( $building_details['exposure'] ) ) {
            $building_rows[] = [
                'label' => __( 'Ekspozycja', 'estate-office' ),
                'value' => $this->format_list_display( $building_details['exposure'], $this->get_exposure_options() ),
                'html'  => false,
            ];
        }
        if ( ! empty( $building_details['view'] ) && is_array( $building_details['view'] ) ) {
            $building_rows[] = [
                'label' => __( 'Widok', 'estate-office' ),
                'value' => $this->format_list_display( $building_details['view'], $this->get_view_options() ),
                'html'  => false,
            ];
        }
        if ( array_key_exists( 'attic', $building_details ) ) {
            $building_rows[] = [ 'label' => __( 'Poddasze', 'estate-office' ), 'value' => $this->format_boolean_display( ! empty( $building_details['attic'] ) ), 'html' => false ];
        }
        if ( array_key_exists( 'multi_level', $building_details ) ) {
            $building_rows[] = [ 'label' => __( 'Wielopoziomowe', 'estate-office' ), 'value' => $this->format_boolean_display( ! empty( $building_details['multi_level'] ) ), 'html' => false ];
        }
        if ( ! empty( $building_details['layout'] ) && is_array( $building_details['layout'] ) ) {
            $building_rows[] = [
                'label' => __( 'Rozkład', 'estate-office' ),
                'value' => $this->format_list_display( $building_details['layout'], $this->get_layout_options() ),
                'html'  => false,
            ];
        }
        if ( ! empty( $building_details['kitchen_type'] ) ) {
            $building_rows[] = [
                'label' => __( 'Typ kuchni', 'estate-office' ),
                'value' => $this->get_kitchen_types()[ $building_details['kitchen_type'] ] ?? $building_details['kitchen_type'],
                'html'  => false,
            ];
        }
        $parking_available = isset( $building_details['parking']['available'] ) ? (bool) $building_details['parking']['available'] : false;
        $building_rows[]   = [ 'label' => __( 'Miejsce parkingowe', 'estate-office' ), 'value' => $this->format_boolean_display( $parking_available ), 'html' => false ];
        if ( $parking_available && ! empty( $building_details['parking']['types'] ) && is_array( $building_details['parking']['types'] ) ) {
            $building_rows[] = [
                'label' => __( 'Rodzaje miejsc parkingowych', 'estate-office' ),
                'value' => $this->format_list_display( $building_details['parking']['types'], $this->get_parking_types() ),
                'html'  => false,
            ];
        }

        if ( empty( $building_rows ) ) {
            echo '<p>' . esc_html__( 'Brak danych budynku.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            foreach ( $building_rows as $row ) {
                $value = (string) ( $row['value'] ?? '' );
                if ( '' === trim( $value ) ) {
                    continue;
                }
                echo '<tr><th style="width:35%;">' . esc_html( (string) $row['label'] ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
            }
            echo '</table>';
        }
        echo '</section>';

        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Media i instalacje', 'estate-office' ) . '</h2>';
        $media_rows = [];
        if ( ! empty( $media_details['heating'] ) ) {
            $media_rows[] = [ 'label' => __( 'Ogrzewanie', 'estate-office' ), 'value' => $this->get_heating_types()[ $media_details['heating'] ] ?? $media_details['heating'] ];
        }
        if ( ! empty( $media_details['water'] ) ) {
            $media_rows[] = [ 'label' => __( 'Woda', 'estate-office' ), 'value' => $this->get_water_types()[ $media_details['water'] ] ?? $media_details['water'] ];
        }
        if ( ! empty( $media_details['sewage'] ) ) {
            $media_rows[] = [ 'label' => __( 'Kanalizacja', 'estate-office' ), 'value' => $this->get_sewage_types()[ $media_details['sewage'] ] ?? $media_details['sewage'] ];
        }
        if ( array_key_exists( 'gas', $media_details ) ) {
            $media_rows[] = [ 'label' => __( 'Gaz', 'estate-office' ), 'value' => $this->format_boolean_display( ! empty( $media_details['gas'] ) ) ];
        }

        if ( empty( $media_rows ) ) {
            echo '<p>' . esc_html__( 'Brak danych o mediach.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            foreach ( $media_rows as $row ) {
                echo '<tr><th style="width:35%;">' . esc_html( (string) $row['label'] ) . '</th><td>' . esc_html( (string) $row['value'] ) . '</td></tr>';
            }
            echo '</table>';
        }
        echo '</section>';

        $amenities_label = $this->format_list_display( $amenities, $this->get_amenities_options() );
        $equipment_items = [];
        if ( ! empty( $equipment['items'] ) && is_array( $equipment['items'] ) ) {
            $equipment_items = $this->format_list_display( $equipment['items'], $this->get_equipment_items() );
        }
        $equipment_level = '';
        if ( ! empty( $equipment['level'] ) ) {
            $equipment_level = $this->get_equipment_levels()[ $equipment['level'] ] ?? $equipment['level'];
        }

        if ( $amenities_label || $equipment_level || $equipment_items ) {
            echo '<section class="estate-office-card">';
            echo '<h2>' . esc_html__( 'Udogodnienia i wyposażenie', 'estate-office' ) . '</h2>';
            echo '<table class="widefat fixed striped">';
            if ( $amenities_label ) {
                echo '<tr><th style="width:35%;">' . esc_html__( 'Udogodnienia', 'estate-office' ) . '</th><td>' . esc_html( $amenities_label ) . '</td></tr>';
            }
            if ( $equipment_level ) {
                echo '<tr><th style="width:35%;">' . esc_html__( 'Umeblowanie', 'estate-office' ) . '</th><td>' . esc_html( $equipment_level ) . '</td></tr>';
            }
            if ( $equipment_items ) {
                echo '<tr><th style="width:35%;">' . esc_html__( 'Wyposażenie', 'estate-office' ) . '</th><td>' . esc_html( $equipment_items ) . '</td></tr>';
            }
            echo '</table>';
            echo '</section>';
        }

        $additional_rows = $this->format_additional_areas_display( $additional_areas );
        if ( ! empty( $additional_rows ) ) {
            echo '<section class="estate-office-card">';
            echo '<h2>' . esc_html__( 'Powierzchnie dodatkowe', 'estate-office' ) . '</h2>';
            echo '<table class="widefat fixed striped">';
            foreach ( $additional_rows as $label => $value ) {
                echo '<tr><th style="width:35%;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
            }
            echo '</table>';
            echo '</section>';
        }

        echo '</div>';

        echo '<div style="flex:1 1 360px;min-width:320px;">';
        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Opis nieruchomości', 'estate-office' ) . '</h2>';
        if ( ! empty( $property['description'] ) ) {
            echo '<div class="estate-office-property-description">' . wp_kses_post( wpautop( (string) $property['description'] ) ) . '</div>';
        } else {
            echo '<p>' . esc_html__( 'Brak opisu.', 'estate-office' ) . '</p>';
        }
        echo '</section>';

        $flags_map = [
            'new_offer'       => __( 'Nowa oferta', 'estate-office' ),
            'exclusive_offer' => __( 'Wyłączność', 'estate-office' ),
            'sold_offer'      => __( 'Sprzedane', 'estate-office' ),
            'rented_offer'    => __( 'Wynajęte', 'estate-office' ),
            'new_price'       => __( 'Nowa cena', 'estate-office' ),
            'commission_free' => __( 'Bez prowizji', 'estate-office' ),
            'mls_offer'       => __( 'Oferta MLS', 'estate-office' ),
            'premium_offer'   => __( 'Oferta premium', 'estate-office' ),
        ];
        $active_flags = [];
        foreach ( $flags_map as $flag_key => $flag_label ) {
            if ( ! empty( $property[ $flag_key ] ) ) {
                $active_flags[] = $flag_label;
            }
        }

        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Znaczniki i eksport', 'estate-office' ) . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<tr><th style="width:35%;">' . esc_html__( 'Eksport na WWW', 'estate-office' ) . '</th><td>' . esc_html( $this->format_boolean_display( ! empty( $property['export_web'] ) ) ) . '</td></tr>';
        echo '<tr><th style="width:35%;">' . esc_html__( 'Eksport na portale', 'estate-office' ) . '</th><td>' . esc_html( $this->format_boolean_display( ! empty( $property['export_portals'] ) ) ) . '</td></tr>';
        if ( ! empty( $active_flags ) ) {
            echo '<tr><th style="width:35%;">' . esc_html__( 'Aktywne znaczniki', 'estate-office' ) . '</th><td>' . esc_html( implode( ', ', $active_flags ) ) . '</td></tr>';
        }
        echo '</table>';
        echo '</section>';

        $gallery_ids = is_array( $prepared['gallery'] ) ? array_filter( array_map( 'absint', $prepared['gallery'] ) ) : [];
        if ( ! empty( $gallery_ids ) ) {
            echo '<section class="estate-office-card">';
            echo '<h2>' . esc_html__( 'Galeria', 'estate-office' ) . '</h2>';
            echo '<div class="estate-office-property-gallery" style="display:flex;gap:0.75rem;flex-wrap:wrap;">';
            foreach ( $gallery_ids as $attachment_id ) {
                $thumbnail = wp_get_attachment_image( $attachment_id, 'thumbnail' );
                $full_url  = wp_get_attachment_url( $attachment_id );
                if ( $thumbnail && $full_url ) {
                    echo '<a href="' . esc_url( $full_url ) . '" target="_blank" rel="noopener noreferrer" class="estate-office-gallery-thumb">' . $thumbnail . '</a>';
                }
            }
            echo '</div>';
            echo '</section>';
        }

        $media_links = [];
        if ( ! empty( $property['video_url'] ) ) {
            $media_links[] = '<a href="' . esc_url( $property['video_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Zobacz film', 'estate-office' ) . '</a>';
        }
        if ( ! empty( $property['virtual_tour_url'] ) ) {
            $media_links[] = '<a href="' . esc_url( $property['virtual_tour_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Wirtualny spacer', 'estate-office' ) . '</a>';
        }
        $plans = [];
        if ( ! empty( $property['floor_plan_2d'] ) ) {
            $plan_url = wp_get_attachment_url( (int) $property['floor_plan_2d'] );
            if ( $plan_url ) {
                $plans[] = '<a href="' . esc_url( $plan_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Rzut 2D', 'estate-office' ) . '</a>';
            }
        }
        if ( ! empty( $property['floor_plan_3d'] ) ) {
            $plan_url = wp_get_attachment_url( (int) $property['floor_plan_3d'] );
            if ( $plan_url ) {
                $plans[] = '<a href="' . esc_url( $plan_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Rzut 3D', 'estate-office' ) . '</a>';
            }
        }
        if ( ! empty( $media_links ) || ! empty( $plans ) ) {
            echo '<section class="estate-office-card">';
            echo '<h2>' . esc_html__( 'Multimedia i rzuty', 'estate-office' ) . '</h2>';
            if ( ! empty( $media_links ) ) {
                echo '<p>' . implode( '<br />', array_map( 'wp_kses_post', $media_links ) ) . '</p>';
            }
            if ( ! empty( $plans ) ) {
                echo '<p>' . implode( '<br />', array_map( 'wp_kses_post', $plans ) ) . '</p>';
            }
            echo '</section>';
        }

        echo '<section class="estate-office-card">';
        echo '<h2>' . esc_html__( 'Powiązani klienci', 'estate-office' ) . '</h2>';
        if ( empty( $clients ) ) {
            echo '<p>' . esc_html__( 'Brak przypisanych klientów.', 'estate-office' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Klient', 'estate-office' ) . '</th><th>' . esc_html__( 'Rola', 'estate-office' ) . '</th><th>' . esc_html__( 'Akcje', 'estate-office' ) . '</th></tr></thead><tbody>';
            foreach ( $clients as $client ) {
                $client_name = 'person' === ( $client['client_type'] ?? '' )
                    ? trim( (string) ( $client['first_name'] ?? '' ) . ' ' . ( $client['last_name'] ?? '' ) )
                    : ( $client['company_name'] ?? '' );
                $client_name = $client_name ?: __( 'Klient', 'estate-office' );
                $client_link = add_query_arg(
                    [
                        'page'      => 'estate-office-clients',
                        'action'    => 'view',
                        'client_id' => (int) $client['id'],
                    ],
                    admin_url( 'admin.php' )
                );
                echo '<tr>';
                echo '<td><a href="' . esc_url( $client_link ) . '">' . esc_html( $client_name ) . '</a></td>';
                echo '<td>' . esc_html( $client['role'] ?? '' ) . '</td>';
                echo '<td><a href="' . esc_url( $client_link ) . '">' . esc_html__( 'Przejdź do profilu', 'estate-office' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

        if ( $contract ) {
            echo '<section class="estate-office-card">';
            echo '<h2>' . esc_html__( 'Podsumowanie umowy', 'estate-office' ) . '</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<tr><th style="width:40%;">' . esc_html__( 'Numer', 'estate-office' ) . '</th><td>' . esc_html( $contract['contract_number'] ?? '' ) . '</td></tr>';
            if ( ! empty( $contract['transaction_type'] ) ) {
                $contract_types = $this->get_transaction_types();
                $contract_label = $contract_types[ $contract['transaction_type'] ] ?? $contract['transaction_type'];
                echo '<tr><th style="width:40%;">' . esc_html__( 'Typ transakcji', 'estate-office' ) . '</th><td>' . esc_html( $contract_label ) . '</td></tr>';
            }
            if ( ! empty( $contract['current_stage'] ) ) {
                echo '<tr><th style="width:40%;">' . esc_html__( 'Etap', 'estate-office' ) . '</th><td>' . esc_html( $contract['current_stage'] ) . '</td></tr>';
            }
            $contract_link = add_query_arg(
                [
                    'page'        => 'estate-office-contracts',
                    'action'      => 'view',
                    'contract_id' => (int) $contract['id'],
                ],
                admin_url( 'admin.php' )
            );
            echo '<tr><th style="width:40%;">' . esc_html__( 'Akcje', 'estate-office' ) . '</th><td><a class="button button-secondary" href="' . esc_url( $contract_link ) . '">' . esc_html__( 'Przejdź do umowy', 'estate-office' ) . '</a></td></tr>';
            echo '</table>';
            echo '</section>';
        }

        echo '</div>';

        echo '</div>';

        echo '<p class="estate-office-profile-actions">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '" class="button">' . esc_html__( 'Powrót do listy', 'estate-office' ) . '</a> ';
        if ( current_user_can( 'edit_estate_office_properties' ) ) {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=edit&property_id=' . $property_id ) ) . '" class="button button-primary">' . esc_html__( 'Edytuj', 'estate-office' ) . '</a> ';
        }
        if ( current_user_can( 'delete_estate_office_properties' ) ) {
            $delete_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=estate_office_delete_property&property_id=' . $property_id ),
                'estate_office_delete_property_' . $property_id
            );
            echo '<a href="' . esc_url( $delete_url ) . '" class="button button-link-delete">' . esc_html__( 'Usuń', 'estate-office' ) . '</a>';
        }
        echo '</p>';

        echo '</div>';
    }

    /**
     * Renderuje formularz tworzenia/edycji nieruchomości.
     *
     * @param array<string,mixed>|null $property Dane nieruchomości.
     *
     * @return void
     */
    private function render_form( ?array $property = null ) : void {
        $is_edit = null !== $property;
        $data    = $this->prepare_property_for_form( $property );

        $wizard_mode        = isset( $_GET['wizard'] ) ? sanitize_key( wp_unslash( $_GET['wizard'] ) ) : '';
        $wizard_step        = isset( $_GET['wizard_step'] ) ? sanitize_key( wp_unslash( $_GET['wizard_step'] ) ) : '';
        $wizard_contract_id = isset( $_GET['wizard_contract_id'] ) ? absint( $_GET['wizard_contract_id'] ) : 0;
        $wizard_active      = ! $is_edit && 'contract' === $wizard_mode && 'property' === $wizard_step && $wizard_contract_id > 0;

        if ( $wizard_active && 0 === $data['contract_id'] ) {
            $data['contract_id'] = $wizard_contract_id;
        }

        $heading = $is_edit ? __( 'Edytuj nieruchomość', 'estate-office' ) : __( 'Dodaj nieruchomość', 'estate-office' );
        if ( $wizard_active ) {
            $heading = __( 'Nowa umowa – etap 3/3: Dodaj nieruchomość', 'estate-office' );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $heading ) . '</h1>';
        if ( $wizard_active ) {
            echo '<p class="description">' . esc_html__( 'Uzupełnij dane nieruchomości, aby zakończyć proces tworzenia umowy.', 'estate-office' ) . '</p>';
        }

        if ( isset( $_GET['estate-office-message'] ) ) {
            $status  = isset( $_GET['estate-office-status'] ) ? sanitize_key( wp_unslash( $_GET['estate-office-status'] ) ) : 'updated';
            $message = sanitize_text_field( wp_unslash( $_GET['estate-office-message'] ) );
            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $status ), esc_html( $message ) );
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="estate-office-admin-form estate-office-property-form">';
        wp_nonce_field( 'estate_office_save_property', 'estate_office_nonce' );
        echo '<input type="hidden" name="action" value="estate_office_save_property" />';
        echo '<input type="hidden" name="property_id" value="' . esc_attr( $data['id'] ) . '" />';
        if ( $wizard_active ) {
            echo '<input type="hidden" name="wizard" value="contract" />';
            echo '<input type="hidden" name="wizard_step" value="property" />';
            echo '<input type="hidden" name="wizard_contract_id" value="' . esc_attr( $wizard_contract_id ) . '" />';
        }

        echo '<div class="estate-office-property-sections">';

        echo '<h2>' . esc_html__( 'Dane podstawowe', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-property-grid">';
        $this->render_input_field( 'listing_number', __( 'Numer oferty', 'estate-office' ), $data['listing_number'], 'text', [ 'placeholder' => __( 'Automatycznie jeśli puste', 'estate-office' ) ] );
        $this->render_input_field( 'title', __( 'Tytuł oferty', 'estate-office' ), $data['title'], 'text', [ 'required' => 'required' ] );
        $this->render_select_field(
            'contract_id',
            __( 'Powiązana umowa', 'estate-office' ),
            (string) $data['contract_id'],
            $this->get_contract_select_options( (int) $data['contract_id'] )
        );
        $agent_description = '';
        if ( ! $this->can_assign_all_agents() ) {
            if ( $this->get_current_user_agent_id() > 0 ) {
                $agent_description = __( 'Możesz przypisać jedynie siebie jako opiekuna.', 'estate-office' );
            } else {
                $agent_description = __( 'Brak powiązanego profilu agenta dla Twojego konta. Skontaktuj się z administratorem.', 'estate-office' );
            }
        }
        $this->render_select_field(
            'agent_id',
            __( 'Opiekun (agent)', 'estate-office' ),
            (string) $data['agent_id'],
            $this->get_agent_select_options(),
            false,
            [],
            [],
            $agent_description
        );
        $transaction_locked = ! empty( $data['transaction_type_locked'] );
        $this->render_select_field(
            'transaction_type',
            __( 'Typ transakcji', 'estate-office' ),
            $data['transaction_type'],
            $this->get_transaction_types(),
            true,
            [],
            $transaction_locked ? [ 'disabled' => 'disabled' ] : [],
            $transaction_locked ? __( 'Typ transakcji wynika z powiązanej umowy i nie może być zmieniony.', 'estate-office' ) : ''
        );
        if ( $transaction_locked ) {
            printf( '<input type="hidden" name="transaction_type" value="%s" />', esc_attr( $data['transaction_type'] ) );
        }
        $this->render_select_field( 'property_type', __( 'Rodzaj nieruchomości', 'estate-office' ), $data['property_type'], $this->get_property_types(), true );
        $this->render_select_field( 'house_type', __( 'Typ domu', 'estate-office' ), $data['house_type'], $this->get_house_types(), false, [ 'data-property-type' => 'DOM' ] );
        $this->render_select_field( 'ownership_status', __( 'Stan prawny', 'estate-office' ), $data['ownership_status'], $this->get_ownership_status_options(), true );
        $ownership_wrapper_attr = [];
        if ( 'INNE' !== $data['ownership_status'] ) {
            $ownership_wrapper_attr['class'] = 'is-hidden';
        }
        $this->render_input_field(
            'ownership_status_custom',
            __( 'Stan prawny – opis', 'estate-office' ),
            $data['ownership_status_custom'],
            'text',
            [ 'data-ownership-custom' => '1', 'placeholder' => __( 'Podaj stan prawny', 'estate-office' ) ],
            $ownership_wrapper_attr
        );
        $this->render_input_field( 'price', __( 'Cena', 'estate-office' ), $data['price'], 'number', [ 'step' => '0.01', 'min' => '0' ] );
        $this->render_select_field( 'price_currency', __( 'Waluta', 'estate-office' ), $data['price_currency'], $this->get_currency_options(), false );
        $this->render_input_field( 'price_period', __( 'Okres rozliczeniowy', 'estate-office' ), $data['price_period'], 'text' );
        $this->render_input_field( 'administrative_rent', __( 'Czynsz administracyjny', 'estate-office' ), $data['administrative_rent'], 'number', [ 'step' => '0.01', 'min' => '0' ] );
        $this->render_input_field( 'area_total', __( 'Powierzchnia (m²)', 'estate-office' ), $data['area_total'], 'number', [ 'step' => '0.01', 'min' => '0' ] );
        $this->render_readonly_field(
            'price_per_sqm_display',
            __( 'Cena za m²', 'estate-office' ),
            $data['price_per_sqm_display'],
            [ 'data-price-per-sqm' => 'display' ],
            __( 'Wyliczana automatycznie na podstawie ceny i metrażu.', 'estate-office' )
        );
        $this->render_input_field( 'area_plot', __( 'Powierzchnia działki (m²)', 'estate-office' ), $data['area_plot'], 'number', [ 'step' => '0.01', 'min' => '0' ], [ 'data-property-type' => 'DOM,DZIAŁKA' ] );
        $this->render_input_field( 'rooms', __( 'Liczba pokoi', 'estate-office' ), $data['rooms'], 'number', [ 'min' => '0' ], [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_input_field( 'bedrooms', __( 'Liczba sypialni', 'estate-office' ), $data['bedrooms'], 'number', [ 'min' => '0' ], [ 'data-property-type' => 'MIESZKANIE,DOM' ] );
        $this->render_input_field( 'bathrooms', __( 'Łazienki', 'estate-office' ), $data['bathrooms'], 'number', [ 'min' => '0' ], [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_input_field( 'toilets', __( 'Toalety', 'estate-office' ), $data['toilets'], 'number', [ 'min' => '0' ], [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_input_field( 'year_built', __( 'Rok budowy', 'estate-office' ), $data['year_built'], 'number', [ 'min' => '1800', 'max' => gmdate( 'Y' ) ], [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_input_field( 'floor', __( 'Piętro', 'estate-office' ), $data['floor'], 'number', [ 'min' => '-2', 'max' => '60' ], [ 'data-property-type' => 'MIESZKANIE,LOKAL' ] );
        $this->render_input_field( 'total_floors', __( 'Liczba pięter budynku', 'estate-office' ), $data['total_floors'], 'number', [ 'min' => '0' ], [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_select_field( 'plot_shape', __( 'Kształt działki', 'estate-office' ), $data['plot_shape'], $this->get_plot_shapes(), false, [ 'data-property-type' => 'DZIAŁKA' ] );
        $this->render_input_field( 'plot_length', __( 'Długość działki (m)', 'estate-office' ), $data['plot_length'], 'number', [ 'step' => '0.01', 'min' => '0' ], [ 'data-property-type' => 'DZIAŁKA' ] );
        $this->render_input_field( 'plot_width', __( 'Szerokość działki (m)', 'estate-office' ), $data['plot_width'], 'number', [ 'step' => '0.01', 'min' => '0' ], [ 'data-property-type' => 'DZIAŁKA' ] );
        $this->render_textarea_field( 'plot_dimensions_note', __( 'Opis wymiarów działki', 'estate-office' ), $data['plot_dimensions_note'], [ 'data-property-type' => 'DZIAŁKA' ] );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Adres i geolokalizacja', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-property-grid">';
        $this->render_input_field( 'street', __( 'Ulica', 'estate-office' ), $data['street'], 'text' );
        $this->render_input_field( 'street_number', __( 'Numer', 'estate-office' ), $data['street_number'], 'text' );
        $this->render_input_field( 'apartment_number', __( 'Lokal', 'estate-office' ), $data['apartment_number'], 'text' );
        $this->render_input_field( 'postal_code', __( 'Kod pocztowy', 'estate-office' ), $data['postal_code'], 'text', [ 'required' => 'required' ] );
        $this->render_input_field( 'district', __( 'Dzielnica', 'estate-office' ), $data['district'], 'text' );
        $this->render_input_field( 'city', __( 'Miasto', 'estate-office' ), $data['city'], 'text', [ 'required' => 'required' ] );
        $this->render_input_field( 'voivodeship', __( 'Województwo', 'estate-office' ), $data['voivodeship'], 'text' );
        $this->render_input_field( 'county', __( 'Powiat', 'estate-office' ), $data['county'], 'text', [], [ 'data-property-type' => 'DOM,DZIAŁKA' ] );
        $this->render_input_field( 'precinct', __( 'Obręb', 'estate-office' ), $data['precinct'], 'text', [], [ 'data-property-type' => 'DOM,DZIAŁKA' ] );
        $this->render_input_field( 'plot_number', __( 'Numer działki', 'estate-office' ), $data['plot_number'], 'text', [], [ 'data-property-type' => 'DOM,DZIAŁKA' ] );
        $this->render_input_field( 'land_register_number', __( 'Numer księgi wieczystej', 'estate-office' ), $data['land_register_number'], 'text' );
        $this->render_checkbox_field( 'land_register_missing', __( 'Brak księgi wieczystej', 'estate-office' ), $data['land_register_missing'] );
        $this->render_input_field( 'latitude', __( 'Szerokość geogr.', 'estate-office' ), $data['latitude'], 'text' );
        $this->render_input_field( 'longitude', __( 'Długość geogr.', 'estate-office' ), $data['longitude'], 'text' );
        $this->render_input_field( 'google_place_id', __( 'Google Place ID', 'estate-office' ), $data['google_place_id'], 'text' );
        $this->render_map_field( $data );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Opis oferty', 'estate-office' ) . '</h2>';
        wp_editor(
            $data['description'],
            'estate_office_property_description',
            [
                'textarea_name' => 'description',
                'textarea_rows' => 8,
            ]
        );

        echo '<h2>' . esc_html__( 'Szczegóły budynku', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-property-grid">';
        $this->render_select_field( 'building_finish_state', __( 'Stan wykończenia', 'estate-office' ), $data['building_details']['finish_state'], $this->get_finish_states(), false, [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_checkboxes_field( 'building_exposure', __( 'Ekspozycja', 'estate-office' ), $data['building_details']['exposure'], $this->get_exposure_options(), [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_checkboxes_field( 'building_view', __( 'Widok', 'estate-office' ), $data['building_details']['view'], $this->get_view_options(), [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_checkbox_field( 'building_attic', __( 'Poddasze', 'estate-office' ), $data['building_details']['attic'], [ 'data-property-type' => 'DOM' ] );
        $this->render_checkbox_field( 'building_multi_level', __( 'Wielopoziomowe', 'estate-office' ), $data['building_details']['multi_level'], [ 'data-property-type' => 'MIESZKANIE' ] );
        $this->render_checkboxes_field( 'building_layout', __( 'Rozkład', 'estate-office' ), $data['building_details']['layout'], $this->get_layout_options(), [ 'data-property-type' => 'MIESZKANIE,DOM,LOKAL' ] );
        $this->render_select_field( 'building_kitchen_type', __( 'Rodzaj kuchni', 'estate-office' ), $data['building_details']['kitchen_type'], $this->get_kitchen_types(), false, [ 'data-property-type' => 'MIESZKANIE,DOM' ] );
        $this->render_checkbox_field( 'parking_available', __( 'Miejsce parkingowe', 'estate-office' ), $data['building_details']['parking']['available'] );
        $this->render_checkboxes_field( 'parking_types', __( 'Rodzaje parkingu', 'estate-office' ), $data['building_details']['parking']['types'], $this->get_parking_types() );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Media i udogodnienia', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-property-grid">';
        $this->render_select_field( 'media_heating', __( 'Ogrzewanie', 'estate-office' ), $data['media']['heating'], $this->get_heating_types(), false );
        $this->render_select_field( 'media_water', __( 'Woda', 'estate-office' ), $data['media']['water'], $this->get_water_types(), false );
        $this->render_select_field( 'media_sewage', __( 'Kanalizacja', 'estate-office' ), $data['media']['sewage'], $this->get_sewage_types(), false );
        $this->render_checkbox_field( 'media_gas', __( 'Gaz', 'estate-office' ), $data['media']['gas'] );
        $this->render_checkboxes_field( 'amenities', __( 'Udogodnienia', 'estate-office' ), $data['amenities'], $this->get_amenities_options() );
        $this->render_select_field( 'equipment_level', __( 'Poziom umeblowania', 'estate-office' ), $data['equipment']['level'], $this->get_equipment_levels(), false );
        $this->render_checkboxes_field( 'equipment_items', __( 'Wyposażenie', 'estate-office' ), $data['equipment']['items'], $this->get_equipment_items() );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Powierzchnie dodatkowe', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-property-grid">';
        $this->render_additional_area_field( 'balcony', __( 'Balkon', 'estate-office' ), $data['additional_areas']['balcony'] );
        $this->render_additional_area_field( 'terrace', __( 'Taras', 'estate-office' ), $data['additional_areas']['terrace'] );
        $this->render_additional_area_field( 'cellar', __( 'Piwnica', 'estate-office' ), $data['additional_areas']['cellar'], false );
        $this->render_additional_area_field( 'storage', __( 'Komórka lokatorska', 'estate-office' ), $data['additional_areas']['storage'], false );
        $this->render_additional_area_field( 'garden', __( 'Ogródek', 'estate-office' ), $data['additional_areas']['garden'], false );
        echo '</div>';

        echo '<h2>' . esc_html__( 'Galeria i multimedia', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-property-grid estate-office-property-media">';
        echo '<div class="field">';
        echo '<label for="estate-office-gallery">' . esc_html__( 'ID zdjęć (oddzielone przecinkami)', 'estate-office' ) . '</label>';
        printf( '<input type="text" id="estate-office-gallery" name="gallery" value="%s" />', esc_attr( implode( ',', $data['gallery'] ) ) );
        printf( '<button id="estate-office-select-gallery" class="button" data-title="%s">%s</button>', esc_attr__( 'Wybierz zdjęcia', 'estate-office' ), esc_html__( 'Biblioteka mediów', 'estate-office' ) );
        echo '</div>';
        echo '<div class="field">';
        echo '<label for="estate-office-plan-2d">' . esc_html__( 'Rzut 2D (ID)', 'estate-office' ) . '</label>';
        printf( '<input type="text" id="estate-office-plan-2d" name="floor_plan_2d" value="%s" />', esc_attr( $data['floor_plan_2d'] ) );
        printf( '<button id="estate-office-select-plan-2d" class="button" data-title="%s">%s</button>', esc_attr__( 'Wybierz rzut 2D', 'estate-office' ), esc_html__( 'Biblioteka mediów', 'estate-office' ) );
        echo '</div>';
        echo '<div class="field">';
        echo '<label for="estate-office-plan-3d">' . esc_html__( 'Rzut 3D (ID)', 'estate-office' ) . '</label>';
        printf( '<input type="text" id="estate-office-plan-3d" name="floor_plan_3d" value="%s" />', esc_attr( $data['floor_plan_3d'] ) );
        printf( '<button id="estate-office-select-plan-3d" class="button" data-title="%s">%s</button>', esc_attr__( 'Wybierz rzut 3D', 'estate-office' ), esc_html__( 'Biblioteka mediów', 'estate-office' ) );
        echo '</div>';
        $this->render_gallery_preview( $data['gallery'] );
        $this->render_input_field( 'video_url', __( 'Link do filmu', 'estate-office' ), $data['video_url'], 'url' );
        $this->render_input_field( 'virtual_tour_url', __( 'Link do wirtualnego spaceru', 'estate-office' ), $data['virtual_tour_url'], 'url' );
        echo '</div>';

        $property_custom_fields = Estate_Office_Dynamic_Fields::get_field_map( 'property' );
        if ( ! empty( $property_custom_fields ) ) {
            echo '<h2>' . esc_html__( 'Dodatkowe pola', 'estate-office' ) . '</h2>';
            echo '<div class="estate-office-property-grid estate-office-property-custom-fields">';
            foreach ( $property_custom_fields as $field_key => $label ) {
                $value = $data['custom_fields'][ $field_key ] ?? '';
                $this->render_input_field(
                    'custom_fields_' . $field_key,
                    $label,
                    $value,
                    'text',
                    [
                        'name' => 'custom_fields[' . $field_key . ']',
                    ]
                );
            }
            echo '</div>';
        }

        echo '<h2>' . esc_html__( 'Znaczniki i eksport', 'estate-office' ) . '</h2>';
        echo '<div class="estate-office-flags">';
        $this->render_checkbox_field( 'export_web', __( 'Eksport na WWW', 'estate-office' ), $data['export_web'] );
        $this->render_checkbox_field( 'export_portals', __( 'Eksport na portale', 'estate-office' ), $data['export_portals'] );
        $this->render_checkbox_field( 'new_offer', __( 'Nowa oferta (naklejka 7 dni)', 'estate-office' ), $data['new_offer'] );
        $this->render_checkbox_field( 'exclusive_offer', __( 'Wyłączność', 'estate-office' ), $data['exclusive_offer'] );
        $this->render_checkbox_field( 'sold_offer', __( 'Sprzedane', 'estate-office' ), $data['sold_offer'], [ 'data-transaction-type' => 'SPRZEDAŻ' ] );
        $this->render_checkbox_field( 'rented_offer', __( 'Wynajęte', 'estate-office' ), $data['rented_offer'], [ 'data-transaction-type' => 'WYNAJEM' ] );
        $this->render_checkbox_field( 'new_price', __( 'Nowa cena', 'estate-office' ), $data['new_price'] );
        $this->render_checkbox_field( 'commission_free', __( 'Bez prowizji', 'estate-office' ), $data['commission_free'] );
        $this->render_checkbox_field( 'mls_offer', __( 'Oferta MLS', 'estate-office' ), $data['mls_offer'] );
        $this->render_checkbox_field( 'premium_offer', __( 'Oferta premium', 'estate-office' ), $data['premium_offer'] );
        echo '</div>';

        echo '<div class="estate-office-property-actions">';
        submit_button( $is_edit ? __( 'Zapisz nieruchomość', 'estate-office' ) : __( 'Dodaj nieruchomość', 'estate-office' ), 'primary', 'submit', false );

        $back_url   = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        $back_label = __( 'Powrót do listy', 'estate-office' );

        if ( $wizard_active ) {
            $back_url   = add_query_arg(
                [
                    'page'        => 'estate-office-contracts',
                    'action'      => 'manage-clients',
                    'contract_id' => $wizard_contract_id,
                ],
                admin_url( 'admin.php' )
            );
            $back_label = __( 'Powrót do etapu 2 – klienci', 'estate-office' );
        }

        echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html( $back_label ) . '</a>';

        if ( $is_edit && current_user_can( 'delete_estate_office_properties' ) ) {
            $delete_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=estate_office_delete_property&property_id=' . absint( $data['id'] ) ),
                'estate_office_delete_property_' . absint( $data['id'] )
            );
            echo '<a class="button button-link-delete" href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Usuń nieruchomość', 'estate-office' ) . '</a>';
        }

        echo '</div>';

        echo '</div>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Obsługuje zapis nieruchomości.
     *
     * @return void
     */
    public function handle_save_property() : void {
        if ( ! current_user_can( 'edit_estate_office_properties' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do zapisu nieruchomości.', 'estate-office' ) );
        }

        check_admin_referer( 'estate_office_save_property', 'estate_office_nonce' );

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

        $existing_property     = null;
        $previous_contract_id  = 0;
        if ( $property_id > 0 ) {
            $existing_property = $this->repository->find( $property_id );
            if ( null === $existing_property ) {
                $this->redirect_with_message( 0, __( 'Nie znaleziono wskazanej nieruchomości.', 'estate-office' ), 'error', $redirect_to );
            }

            $previous_contract_id = (int) ( $existing_property['contract_id'] ?? 0 );
        }

        $data = $this->collect_property_input();
        $data['gallery'] = $this->watermark_service->process_gallery( $data['gallery'] );

        if ( $data['contract_id'] > 0 ) {
            $contract = $this->contracts_repository->find( (int) $data['contract_id'] );
            if ( null === $contract ) {
                $this->redirect_with_message( $property_id, __( 'Nie znaleziono powiązanej umowy.', 'estate-office' ), 'error', $redirect_to );
            }

            $contract_agent_id = (int) ( $contract['agent_id'] ?? 0 );
            if ( $contract_agent_id > 0 && $data['agent_id'] <= 0 ) {
                if ( $this->can_assign_all_agents() || $contract_agent_id === $this->get_current_user_agent_id() ) {
                    if ( $this->agent_repository->exists( $contract_agent_id ) ) {
                        $data['agent_id'] = $contract_agent_id;
                    }
                }
            }

            $mapped_type = $this->map_contract_transaction_type( (string) ( $contract['transaction_type'] ?? '' ) );
            if ( '' === $mapped_type ) {
                $this->redirect_with_message( $property_id, __( 'Typ transakcji powiązanej umowy jest nieobsługiwany.', 'estate-office' ), 'error', $redirect_to );
            }

            $data['transaction_type'] = $mapped_type;
        }

        if ( empty( $data['title'] ) || empty( $data['transaction_type'] ) || empty( $data['property_type'] ) ) {
            $this->redirect_with_message( $property_id, __( 'Uzupełnij wymagane pola: tytuł, typ transakcji i rodzaj nieruchomości.', 'estate-office' ), 'error', $redirect_to );
        }

        if ( empty( $data['listing_number'] ) ) {
            $data['listing_number'] = $this->repository->generate_listing_number();
        } else {
            $existing = $this->repository->find_by_listing_number( $data['listing_number'] );
            if ( $existing && (int) $existing['id'] !== $property_id ) {
                $this->redirect_with_message( $property_id, __( 'Podany numer oferty jest już przypisany do innej nieruchomości.', 'estate-office' ), 'error', $redirect_to );
            }
        }

        if ( $property_id ) {
            $result = $this->repository->update( $property_id, $data );
            if ( $result ) {
                $this->sync_property_contract_relation( $property_id, (int) $data['contract_id'], $previous_contract_id );
            }

            $message = $result ? __( 'Nieruchomość została zaktualizowana.', 'estate-office' ) : __( 'Nie udało się zapisać zmian.', 'estate-office' );
            $status  = $result ? 'success' : 'error';
            $this->redirect_with_message( $property_id, $message, $status, $redirect_to );
        }

        $new_id = $this->repository->create( $data );
        if ( $new_id ) {
            $this->sync_property_contract_relation( (int) $new_id, (int) $data['contract_id'], 0 );
            $this->redirect_with_message( (int) $new_id, __( 'Dodano nową nieruchomość.', 'estate-office' ), 'success', $redirect_to );
        }

        $this->redirect_with_message( 0, __( 'Nie udało się dodać nieruchomości.', 'estate-office' ), 'error', $redirect_to );
    }

    /**
     * Obsługuje usuwanie nieruchomości.
     *
     * @return void
     */
    public function handle_delete_property() : void {
        if ( ! current_user_can( 'delete_estate_office_properties' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do usuwania nieruchomości.', 'estate-office' ) );
        }

        $property_id = isset( $_GET['property_id'] ) ? absint( $_GET['property_id'] ) : 0;
        check_admin_referer( 'estate_office_delete_property_' . $property_id );

        if ( ! $property_id ) {
            wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            exit;
        }

        $deleted = $this->repository->delete( $property_id );

        $message = $deleted ? __( 'Nieruchomość została usunięta.', 'estate-office' ) : __( 'Usunięcie nieruchomości nie powiodło się.', 'estate-office' );
        $status  = $deleted ? 'success' : 'error';

        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&estate-office-message=' . rawurlencode( $message ) . '&estate-office-status=' . rawurlencode( $status ) ) );
        exit;
    }

    /**
     * Normalizuje dane nieruchomości dla formularza.
     *
     * @param array<string,mixed>|null $property Dane z bazy.
     *
     * @return array<string,mixed>
     */
    private function prepare_property_for_form( ?array $property ) : array {
        $defaults = [
            'id'                    => 0,
            'listing_number'        => '',
            'contract_id'           => 0,
            'agent_id'              => 0,
            'title'                 => '',
            'transaction_type'      => 'SPRZEDAŻ',
            'transaction_type_locked' => false,
            'property_type'         => 'MIESZKANIE',
            'house_type'            => '',
            'ownership_status'      => '',
            'ownership_status_custom' => '',
            'price'                 => '',
            'price_per_sqm'         => '',
            'price_per_sqm_display' => '',
            'price_currency'        => 'PLN',
            'price_period'          => '',
            'administrative_rent'   => '',
            'area_total'            => '',
            'area_plot'             => '',
            'rooms'                 => '',
            'bedrooms'              => '',
            'bathrooms'             => '',
            'toilets'               => '',
            'year_built'            => '',
            'floor'                 => '',
            'total_floors'          => '',
            'plot_shape'            => '',
            'plot_length'           => '',
            'plot_width'            => '',
            'plot_dimensions_note'  => '',
            'land_register_number'  => '',
            'land_register_missing' => false,
            'street'                => '',
            'street_number'         => '',
            'apartment_number'      => '',
            'postal_code'           => '',
            'district'              => '',
            'city'                  => '',
            'voivodeship'           => '',
            'county'                => '',
            'precinct'              => '',
            'plot_number'           => '',
            'latitude'              => '',
            'longitude'             => '',
            'google_place_id'       => '',
            'description'           => '',
            'video_url'             => '',
            'virtual_tour_url'      => '',
            'export_web'            => false,
            'export_portals'        => false,
            'new_offer'             => false,
            'exclusive_offer'       => false,
            'sold_offer'            => false,
            'rented_offer'          => false,
            'new_price'             => false,
            'commission_free'       => false,
            'mls_offer'             => false,
            'premium_offer'         => false,
            'building_details'      => [
                'finish_state' => '',
                'exposure'     => [],
                'view'         => [],
                'attic'        => false,
                'multi_level'  => false,
                'layout'       => [],
                'kitchen_type' => '',
                'parking'      => [
                    'available' => false,
                    'types'     => [],
                ],
            ],
            'media'                 => [
                'heating' => '',
                'water'   => '',
                'sewage'  => '',
                'gas'     => false,
            ],
            'amenities'             => [],
            'equipment'             => [
                'level' => '',
                'items' => [],
            ],
            'additional_areas'      => [
                'balcony' => [ 'enabled' => false, 'count' => '', 'area' => '' ],
                'terrace' => [ 'enabled' => false, 'count' => '', 'area' => '' ],
                'cellar'  => [ 'enabled' => false, 'area' => '' ],
                'storage' => [ 'enabled' => false, 'area' => '' ],
                'garden'  => [ 'enabled' => false, 'area' => '' ],
            ],
            'gallery'               => [],
            'floor_plan_2d'         => '',
            'floor_plan_3d'         => '',
            'custom_fields'         => Estate_Office_Dynamic_Fields::merge_defaults( 'property', [] ),
        ];

        if ( null === $property ) {
            if ( isset( $_GET['contract_id'] ) ) {
                $defaults['contract_id'] = absint( $_GET['contract_id'] );
            }

            if ( $defaults['contract_id'] > 0 && $defaults['agent_id'] <= 0 ) {
                $contract = $this->contracts_repository->find( $defaults['contract_id'] );
                if ( null !== $contract && ! empty( $contract['agent_id'] ) ) {
                    $defaults['agent_id'] = (int) $contract['agent_id'];
                }
            }

            if ( $defaults['agent_id'] <= 0 ) {
                $defaults['agent_id'] = $this->get_current_user_agent_id();
            }

            if ( $defaults['agent_id'] > 0 ) {
                $this->prime_agent_labels( [ $defaults['agent_id'] ] );
            }

            if ( $defaults['contract_id'] > 0 ) {
                $this->apply_contract_constraints( $defaults );
            }

            return $defaults;
        }

        foreach ( $property as $key => $value ) {
            if ( array_key_exists( $key, $defaults ) ) {
                $defaults[ $key ] = $value;
            }
        }

        $defaults['contract_id'] = absint( $defaults['contract_id'] );
        $defaults['agent_id']    = absint( $defaults['agent_id'] );

        if ( $defaults['agent_id'] > 0 ) {
            $this->prime_agent_labels( [ $defaults['agent_id'] ] );
        }

        if ( $defaults['contract_id'] > 0 ) {
            $this->apply_contract_constraints( $defaults );
        }

        $custom_defaults = [];
        if ( isset( $property['custom_fields'] ) && '' !== $property['custom_fields'] ) {
            $decoded = json_decode( (string) $property['custom_fields'], true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $custom_key => $custom_value ) {
                    if ( is_scalar( $custom_value ) ) {
                        $custom_defaults[ (string) $custom_key ] = (string) $custom_value;
                    }
                }
            }
        }
        $defaults['custom_fields'] = Estate_Office_Dynamic_Fields::merge_defaults( 'property', $custom_defaults );

        foreach ( [ 'building_details', 'media', 'amenities', 'equipment', 'additional_areas', 'gallery' ] as $json_field ) {
            if ( isset( $property[ $json_field ] ) ) {
                $decoded = json_decode( (string) $property[ $json_field ], true );
                if ( is_array( $decoded ) ) {
                    $defaults[ $json_field ] = $decoded;
                }
            }
        }

        $defaults['export_web']       = ! empty( $property['export_web'] );
        $defaults['export_portals']   = ! empty( $property['export_portals'] );
        $defaults['new_offer']        = ! empty( $property['new_offer'] );
        $defaults['exclusive_offer']  = ! empty( $property['exclusive_offer'] );
        $defaults['sold_offer']       = ! empty( $property['sold_offer'] );
        $defaults['rented_offer']     = ! empty( $property['rented_offer'] );
        $defaults['new_price']        = ! empty( $property['new_price'] );
        $defaults['commission_free']  = ! empty( $property['commission_free'] );
        $defaults['mls_offer']        = ! empty( $property['mls_offer'] );
        $defaults['premium_offer']    = ! empty( $property['premium_offer'] );
        $defaults['land_register_missing'] = empty( $property['land_register_number'] ) && ! empty( $property['id'] );

        if ( ! empty( $defaults['gallery'] ) && is_array( $defaults['gallery'] ) ) {
            $defaults['gallery'] = array_map( 'absint', $defaults['gallery'] );
        }

        if ( 'DOM' !== $defaults['property_type'] ) {
            $defaults['house_type'] = '';
        }

        $ownership_options = $this->get_ownership_status_options();
        if ( '' !== $defaults['ownership_status'] && ! isset( $ownership_options[ $defaults['ownership_status'] ] ) ) {
            $defaults['ownership_status_custom'] = $defaults['ownership_status'];
            $defaults['ownership_status']        = 'INNE';
        }

        $price_value      = '' !== $defaults['price'] ? (float) $defaults['price'] : 0.0;
        $area_value       = '' !== $defaults['area_total'] ? (float) $defaults['area_total'] : 0.0;
        $currency_display = $defaults['price_currency'] ?: 'PLN';

        if ( '' !== $defaults['price_per_sqm'] ) {
            $defaults['price_per_sqm_display'] = number_format_i18n( (float) $defaults['price_per_sqm'], 2 ) . ' ' . $currency_display;
        } elseif ( $price_value > 0 && $area_value > 0 ) {
            $computed                             = $price_value / max( 0.01, $area_value );
            $defaults['price_per_sqm_display'] = number_format_i18n( $computed, 2 ) . ' ' . $currency_display;
        }

        return $defaults;
    }

    /**
     * Pobiera dane z formularza.
     *
     * @return array<string,mixed>
     */
    private function collect_property_input() : array {
        $data = [];

        $data['listing_number']  = sanitize_text_field( wp_unslash( $_POST['listing_number'] ?? '' ) );
        $data['contract_id']     = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
        $agent_input             = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
        $data['agent_id']        = $this->sanitize_agent_selection( $agent_input );
        $data['title']           = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $data['transaction_type'] = $this->sanitize_choice( $_POST['transaction_type'] ?? '', array_keys( $this->get_transaction_types() ) );
        $data['property_type']    = $this->sanitize_choice( $_POST['property_type'] ?? '', array_keys( $this->get_property_types() ) );
        $data['house_type']        = $this->sanitize_choice( $_POST['house_type'] ?? '', array_keys( $this->get_house_types() ) );
        $ownership_choice          = $this->sanitize_choice( $_POST['ownership_status'] ?? '', array_keys( $this->get_ownership_status_options() ) );
        $ownership_custom_input    = sanitize_text_field( wp_unslash( $_POST['ownership_status_custom'] ?? '' ) );
        if ( 'INNE' === $ownership_choice ) {
            $data['ownership_status'] = '' !== $ownership_custom_input ? $ownership_custom_input : 'INNE';
        } elseif ( '' === $ownership_choice && '' !== $ownership_custom_input ) {
            $data['ownership_status'] = $ownership_custom_input;
        } else {
            $data['ownership_status'] = $ownership_choice;
        }
        if ( 'DOM' !== $data['property_type'] ) {
            $data['house_type'] = '';
        }
        $data['price_currency']   = $this->sanitize_choice( $_POST['price_currency'] ?? 'PLN', array_keys( $this->get_currency_options() ) );
        $data['price_period']     = sanitize_text_field( wp_unslash( $_POST['price_period'] ?? '' ) );
        $data['administrative_rent'] = $this->get_request_float( 'administrative_rent' );
        $data['price']            = $this->get_request_float( 'price' );
        $data['area_total']       = $this->get_request_float( 'area_total' );
        $data['area_plot']        = $this->get_request_float( 'area_plot' );
        $data['rooms']            = $this->get_request_int( 'rooms' );
        $data['bedrooms']         = $this->get_request_int( 'bedrooms' );
        $data['bathrooms']        = $this->get_request_int( 'bathrooms' );
        $data['toilets']          = $this->get_request_int( 'toilets' );
        $data['year_built']       = $this->get_request_int( 'year_built' );
        $data['floor']            = $this->get_request_int( 'floor' );
        $data['total_floors']     = $this->get_request_int( 'total_floors' );
        $data['plot_shape']       = $this->sanitize_choice( $_POST['plot_shape'] ?? '', array_keys( $this->get_plot_shapes() ) );
        $data['plot_length']      = $this->get_request_float( 'plot_length' );
        $data['plot_width']       = $this->get_request_float( 'plot_width' );
        $data['plot_dimensions_note'] = sanitize_textarea_field( wp_unslash( $_POST['plot_dimensions_note'] ?? '' ) );

        $land_register_missing = ! empty( $_POST['land_register_missing'] );
        $data['land_register_number'] = $land_register_missing ? '' : sanitize_text_field( wp_unslash( $_POST['land_register_number'] ?? '' ) );

        $data['street']           = sanitize_text_field( wp_unslash( $_POST['street'] ?? '' ) );
        $data['street_number']    = sanitize_text_field( wp_unslash( $_POST['street_number'] ?? '' ) );
        $data['apartment_number'] = sanitize_text_field( wp_unslash( $_POST['apartment_number'] ?? '' ) );
        $data['postal_code']      = sanitize_text_field( wp_unslash( $_POST['postal_code'] ?? '' ) );
        $data['district']         = sanitize_text_field( wp_unslash( $_POST['district'] ?? '' ) );
        $data['city']             = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
        $data['voivodeship']      = sanitize_text_field( wp_unslash( $_POST['voivodeship'] ?? '' ) );
        $data['county']           = sanitize_text_field( wp_unslash( $_POST['county'] ?? '' ) );
        $data['precinct']         = sanitize_text_field( wp_unslash( $_POST['precinct'] ?? '' ) );
        $data['plot_number']      = sanitize_text_field( wp_unslash( $_POST['plot_number'] ?? '' ) );
        $data['latitude']         = $this->get_request_float( 'latitude' );
        $data['longitude']        = $this->get_request_float( 'longitude' );
        $data['google_place_id']  = sanitize_text_field( wp_unslash( $_POST['google_place_id'] ?? '' ) );
        $data['description']      = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
        $data['video_url']        = esc_url_raw( wp_unslash( $_POST['video_url'] ?? '' ) );
        $data['virtual_tour_url'] = esc_url_raw( wp_unslash( $_POST['virtual_tour_url'] ?? '' ) );

        $building = [];
        $building['finish_state'] = $this->sanitize_choice( $_POST['building_finish_state'] ?? '', array_keys( $this->get_finish_states() ) );
        $building['exposure']     = $this->sanitize_array_choice( $_POST['building_exposure'] ?? [], array_keys( $this->get_exposure_options() ) );
        $building['view']         = $this->sanitize_array_choice( $_POST['building_view'] ?? [], array_keys( $this->get_view_options() ) );
        $building['attic']        = ! empty( $_POST['building_attic'] );
        $building['multi_level']  = ! empty( $_POST['building_multi_level'] );
        $building['layout']       = $this->sanitize_array_choice( $_POST['building_layout'] ?? [], array_keys( $this->get_layout_options() ) );
        $building['kitchen_type'] = $this->sanitize_choice( $_POST['building_kitchen_type'] ?? '', array_keys( $this->get_kitchen_types() ) );
        $building['parking']      = [
            'available' => ! empty( $_POST['parking_available'] ),
            'types'     => $this->sanitize_array_choice( $_POST['parking_types'] ?? [], array_keys( $this->get_parking_types() ) ),
        ];
        $data['building_details'] = $building;

        $media = [];
        $media['heating'] = $this->sanitize_choice( $_POST['media_heating'] ?? '', array_keys( $this->get_heating_types() ) );
        $media['water']   = $this->sanitize_choice( $_POST['media_water'] ?? '', array_keys( $this->get_water_types() ) );
        $media['sewage']  = $this->sanitize_choice( $_POST['media_sewage'] ?? '', array_keys( $this->get_sewage_types() ) );
        $media['gas']     = ! empty( $_POST['media_gas'] );
        $data['media']    = $media;

        $data['amenities'] = $this->sanitize_array_choice( $_POST['amenities'] ?? [], array_keys( $this->get_amenities_options() ) );

        $equipment = [];
        $equipment['level'] = $this->sanitize_choice( $_POST['equipment_level'] ?? '', array_keys( $this->get_equipment_levels() ) );
        $equipment['items'] = $this->sanitize_array_choice( $_POST['equipment_items'] ?? [], array_keys( $this->get_equipment_items() ) );
        $data['equipment']  = $equipment;

        $data['additional_areas'] = [
            'balcony' => $this->collect_area_data( 'balcony' ),
            'terrace' => $this->collect_area_data( 'terrace' ),
            'cellar'  => $this->collect_area_data( 'cellar', false ),
            'storage' => $this->collect_area_data( 'storage', false ),
            'garden'  => $this->collect_area_data( 'garden', false ),
        ];

        $gallery_raw   = sanitize_text_field( wp_unslash( $_POST['gallery'] ?? '' ) );
        $gallery_parts = array_filter( array_map( 'trim', explode( ',', $gallery_raw ) ) );
        $data['gallery']       = array_map( 'absint', $gallery_parts );
        $data['floor_plan_2d'] = absint( $_POST['floor_plan_2d'] ?? 0 );
        $data['floor_plan_3d'] = absint( $_POST['floor_plan_3d'] ?? 0 );

        $data['export_web']       = ! empty( $_POST['export_web'] ) ? 1 : 0;
        $data['export_portals']   = ! empty( $_POST['export_portals'] ) ? 1 : 0;
        $data['new_offer']        = ! empty( $_POST['new_offer'] ) ? 1 : 0;
        $data['exclusive_offer']  = ! empty( $_POST['exclusive_offer'] ) ? 1 : 0;
        $data['sold_offer']       = ! empty( $_POST['sold_offer'] ) ? 1 : 0;
        $data['rented_offer']     = ! empty( $_POST['rented_offer'] ) ? 1 : 0;
        $data['new_price']        = ! empty( $_POST['new_price'] ) ? 1 : 0;
        $data['commission_free']  = ! empty( $_POST['commission_free'] ) ? 1 : 0;
        $data['mls_offer']        = ! empty( $_POST['mls_offer'] ) ? 1 : 0;
        $data['premium_offer']    = ! empty( $_POST['premium_offer'] ) ? 1 : 0;

        $data['new_offer_until'] = $data['new_offer'] ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + WEEK_IN_SECONDS ) : null;

        $custom_fields_input = [];
        if ( isset( $_POST['custom_fields'] ) && is_array( $_POST['custom_fields'] ) ) {
            foreach ( $_POST['custom_fields'] as $custom_key => $custom_value ) {
                if ( is_scalar( $custom_value ) ) {
                    $custom_fields_input[ (string) $custom_key ] = (string) wp_unslash( $custom_value );
                }
            }
        }
        $data['custom_fields'] = Estate_Office_Dynamic_Fields::sanitize_values( 'property', $custom_fields_input );

        return array_filter(
            $data,
            static function ( $value ) {
                return null !== $value;
            }
        );
    }

    /**
     * Zbiera dane powierzchni dodatkowej.
     *
     * @param string $prefix   Prefiks pola.
     * @param bool   $with_qty Czy uwzględniać ilość.
     *
     * @return array<string,mixed>
     */
    private function collect_area_data( string $prefix, bool $with_qty = true ) : array {
        $enabled = ! empty( $_POST[ $prefix . '_enabled' ] );
        $area    = $this->get_request_float( $prefix . '_area' );
        $data    = [
            'enabled' => $enabled,
            'area'    => $area,
        ];

        if ( $with_qty ) {
            $data['count'] = $this->get_request_int( $prefix . '_count' );
        }

        return $data;
    }

    /**
     * Zwraca tekstową reprezentację wartości logicznej.
     *
     * @param bool $value Wartość.
     *
     * @return string
     */
    private function format_boolean_display( bool $value ) : string {
        return $value ? __( 'Tak', 'estate-office' ) : __( 'Nie', 'estate-office' );
    }

    /**
     * Buduje listę etykiet na podstawie opcji.
     *
     * @param array<int|string,mixed> $values  Wartości.
     * @param array<string,string>    $options Mapowanie dostępnych opcji.
     *
     * @return string
     */
    private function format_list_display( array $values, array $options ) : string {
        $labels = [];

        foreach ( $values as $value ) {
            if ( is_string( $value ) && '' !== $value ) {
                $labels[] = $options[ $value ] ?? $value;
            }
        }

        return empty( $labels ) ? '' : implode( ', ', $labels );
    }

    /**
     * Formatuje powierzchnie dodatkowe do prezentacji.
     *
     * @param array<string,mixed> $areas Dane powierzchni.
     *
     * @return array<string,string>
     */
    private function format_additional_areas_display( array $areas ) : array {
        $labels = [
            'balcony' => __( 'Balkon', 'estate-office' ),
            'terrace' => __( 'Taras', 'estate-office' ),
            'cellar'  => __( 'Piwnica', 'estate-office' ),
            'storage' => __( 'Komórka lokatorska', 'estate-office' ),
            'garden'  => __( 'Ogródek', 'estate-office' ),
        ];

        $output = [];

        foreach ( $labels as $key => $label ) {
            if ( ! isset( $areas[ $key ] ) || ! is_array( $areas[ $key ] ) ) {
                continue;
            }

            $item    = $areas[ $key ];
            $enabled = ! empty( $item['enabled'] );
            $count   = ( isset( $item['count'] ) && null !== $item['count'] ) ? (int) $item['count'] : 0;
            $area    = ( isset( $item['area'] ) && '' !== $item['area'] && null !== $item['area'] ) ? (float) $item['area'] : null;

            if ( ! $enabled && 0 === $count && ( null === $area || $area <= 0 ) ) {
                continue;
            }

            $parts = [];
            $parts[] = $this->format_boolean_display( $enabled );

            if ( $count > 0 && array_key_exists( 'count', $item ) ) {
                /* translators: %d: count */
                $parts[] = sprintf( _n( '%d element', '%d elementy', $count, 'estate-office' ), $count );
            }

            if ( null !== $area && $area > 0 ) {
                /* translators: %s: area */
                $parts[] = sprintf( __( '%s m²', 'estate-office' ), number_format_i18n( $area, 2 ) );
            }

            $output[ $label ] = implode( ', ', $parts );
        }

        return $output;
    }

    /**
     * Modyfikuje dane formularza na podstawie powiązanej umowy.
     *
     * @param array<string,mixed> $defaults Dane formularza.
     *
     * @return void
     */
    private function apply_contract_constraints( array &$defaults ) : void {
        $contract = $this->contracts_repository->find( (int) $defaults['contract_id'] );
        if ( null === $contract ) {
            return;
        }

        $mapped_type = $this->map_contract_transaction_type( (string) ( $contract['transaction_type'] ?? '' ) );
        if ( '' === $mapped_type ) {
            return;
        }

        $defaults['transaction_type']        = $mapped_type;
        $defaults['transaction_type_locked'] = true;
    }

    /**
     * Mapuje typ transakcji z umowy na wartość formularza nieruchomości.
     *
     * @param string $transaction_type Typ transakcji z umowy.
     *
     * @return string
     */
    private function map_contract_transaction_type( string $transaction_type ) : string {
        $normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $transaction_type ) : strtolower( $transaction_type );

        $map = [
            'sprzedaz' => 'SPRZEDAŻ',
            'kupno'    => 'KUPNO',
            'wynajem'  => 'WYNAJEM',
            'najem'    => 'NAJEM',
        ];

        return $map[ $normalized ] ?? '';
    }

    /**
     * Synchronizuje relację nieruchomości z umową.
     *
     * @param int $property_id          ID nieruchomości.
     * @param int $new_contract_id      Aktualny ID umowy.
     * @param int $previous_contract_id Poprzedni ID umowy.
     *
     * @return void
     */
    private function sync_property_contract_relation( int $property_id, int $new_contract_id, int $previous_contract_id ) : void {
        if ( $previous_contract_id > 0 && $previous_contract_id !== $new_contract_id ) {
            $this->contracts_repository->detach_property( $previous_contract_id, $property_id );
        }

        if ( $new_contract_id > 0 && $previous_contract_id !== $new_contract_id ) {
            $this->contracts_repository->attach_property( $new_contract_id, $property_id );
        }

    }

    /**
     * Zwraca listę dostępnych umów dla pola wyboru.
     *
     * @param int $selected Aktualnie wybrana umowa.
     *
     * @return array<string,string>
     */
    private function get_contract_select_options( int $selected ) : array {
        $options = [ '0' => __( 'Brak powiązanej umowy', 'estate-office' ) ];

        $contracts = $this->contracts_repository->paginate(
            [
                'per_page' => 100,
            ]
        );

        $transaction_labels = [
            'sprzedaz' => __( 'Sprzedaż', 'estate-office' ),
            'kupno'    => __( 'Kupno', 'estate-office' ),
            'wynajem'  => __( 'Wynajem', 'estate-office' ),
            'najem'    => __( 'Najem', 'estate-office' ),
        ];

        if ( isset( $contracts['items'] ) && is_array( $contracts['items'] ) ) {
            foreach ( $contracts['items'] as $contract ) {
                $label = sprintf(
                    '%1$s – %2$s',
                    $contract['contract_number'],
                    $transaction_labels[ $contract['transaction_type'] ] ?? strtoupper( (string) $contract['transaction_type'] )
                );
                $options[ (string) $contract['id'] ] = $label;
            }
        }

        if ( $selected > 0 && ! isset( $options[ (string) $selected ] ) ) {
            $contract = $this->contracts_repository->find( $selected );
            if ( $contract ) {
                $options[ (string) $selected ] = sprintf(
                    '%1$s – %2$s',
                    $contract['contract_number'],
                    $transaction_labels[ $contract['transaction_type'] ] ?? strtoupper( (string) $contract['transaction_type'] )
                );
            }
        }

        return $options;
    }

    /**
     * Sanitizuje pojedynczy wybór.
     *
     * @param mixed        $value   Wartość wejściowa.
     * @param array<mixed> $allowed Lista dozwolonych opcji.
     *
     * @return string
     */
    private function sanitize_choice( $value, array $allowed ) : string {
        $value = sanitize_text_field( wp_unslash( (string) $value ) );

        return in_array( $value, $allowed, true ) ? $value : '';
    }

    /**
     * Sanitizuje tablicę wyborów.
     *
     * @param mixed        $value   Wartość wejściowa.
     * @param array<mixed> $allowed Lista dozwolonych opcji.
     *
     * @return array<int,string>
     */
    private function sanitize_array_choice( $value, array $allowed ) : array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        $clean = [];
        foreach ( $value as $item ) {
            $item = sanitize_text_field( wp_unslash( (string) $item ) );
            if ( in_array( $item, $allowed, true ) ) {
                $clean[] = $item;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Pobiera liczbę zmiennoprzecinkową z żądania.
     *
     * @param string $key Klucz tablicy $_POST.
     *
     * @return float|null
     */
    private function get_request_float( string $key ) : ?float {
        if ( ! isset( $_POST[ $key ] ) ) {
            return null;
        }

        $value = str_replace( ',', '.', (string) wp_unslash( $_POST[ $key ] ) );
        $value = trim( $value );

        if ( '' === $value ) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Pobiera liczbę całkowitą z żądania.
     *
     * @param string $key Klucz tablicy $_POST.
     *
     * @return int|null
     */
    private function get_request_int( string $key ) : ?int {
        if ( ! isset( $_POST[ $key ] ) ) {
            return null;
        }

        $value = trim( (string) wp_unslash( $_POST[ $key ] ) );

        if ( '' === $value ) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Przekierowuje z komunikatem.
     *
     * @param int    $property_id ID nieruchomości.
     * @param string $message     Komunikat.
     * @param string $status      Status komunikatu.
     *
     * @return void
     */
    private function redirect_with_message( int $property_id, string $message, string $status, string $redirect_to = '' ) : void {
        if ( ! empty( $redirect_to ) ) {
            $redirect_url = wp_validate_redirect( $redirect_to, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );

            if ( $property_id > 0 ) {
                $redirect_url = add_query_arg(
                    [
                        'view'    => 'property',
                        'item_id' => $property_id,
                    ],
                    $redirect_url
                );
            }

            $redirect_url = add_query_arg(
                [
                    'estate-office-message' => $message,
                    'estate-office-status'  => $status,
                ],
                $redirect_url
            );

            wp_safe_redirect( $redirect_url );
            exit;
        }

        $args = [
            'page'                  => self::PAGE_SLUG,
            'estate-office-message' => $message,
            'estate-office-status'  => $status,
        ];

        if ( $property_id > 0 ) {
            $args['action']      = ( 'error' === $status ) ? 'edit' : 'view';
            $args['property_id'] = $property_id;
        }

        $wizard = isset( $_REQUEST['wizard'] ) ? sanitize_key( wp_unslash( $_REQUEST['wizard'] ) ) : '';
        if ( 'contract' === $wizard ) {
            $args['wizard'] = 'contract';
            $step           = isset( $_REQUEST['wizard_step'] ) ? sanitize_key( wp_unslash( $_REQUEST['wizard_step'] ) ) : '';
            if ( in_array( $step, [ 'property' ], true ) ) {
                $args['wizard_step'] = $step;
            }

            $wizard_contract = isset( $_REQUEST['wizard_contract_id'] ) ? absint( $_REQUEST['wizard_contract_id'] ) : 0;
            if ( $wizard_contract ) {
                $args['wizard_contract_id'] = $wizard_contract;

                if ( ! isset( $args['contract_id'] ) ) {
                    $args['contract_id'] = $wizard_contract;
                }
            }
        }

        wp_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ) );
        exit;
    }

    /**
     * Renderuje pole tekstowe/numeryczne.
     *
     * @param string               $name             Nazwa pola.
     * @param string               $label            Etykieta.
     * @param mixed                $value            Wartość.
     * @param string               $type             Typ inputa.
     * @param array<string,mixed>  $attributes       Dodatkowe atrybuty.
     * @param array<string,string> $wrapper_attr     Atrybuty kontenera.
     *
     * @return void
     */
    private function render_input_field( string $name, string $label, $value, string $type = 'text', array $attributes = [], array $wrapper_attr = [] ) : void {
        echo '<div' . $this->format_wrapper_attributes( $wrapper_attr ) . '>';
        echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';

        $attributes = array_merge(
            [
                'type' => $type,
                'id'   => $name,
                'name' => $name,
            ],
            $attributes
        );

        if ( null !== $value && '' !== $value ) {
            $attributes['value'] = (string) $value;
        }

        echo '<input' . $this->format_attributes( $attributes ) . ' />';
        echo '</div>';
    }

    /**
     * Renderuje pole textarea.
     *
     * @param string               $name         Nazwa.
     * @param string               $label        Etykieta.
     * @param string               $value        Wartość.
     * @param array<string,string> $wrapper_attr Atrybuty kontenera.
     *
     * @return void
     */
    private function render_textarea_field( string $name, string $label, string $value, array $wrapper_attr = [] ) : void {
        echo '<div' . $this->format_wrapper_attributes( $wrapper_attr ) . '>';
        echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
        printf( '<textarea class="large-text" rows="3" name="%1$s" id="%1$s">%2$s</textarea>', esc_attr( $name ), esc_textarea( $value ) );
        echo '</div>';
    }

    /**
     * Renderuje pole select.
     *
     * @param string               $name         Nazwa.
     * @param string               $label        Etykieta.
     * @param string               $value        Aktualna wartość.
     * @param array<string,string> $options      Opcje.
     * @param bool                 $required     Czy wymagane.
     * @param array<string,string> $wrapper_attr Atrybuty kontenera.
     *
     * @return void
     */
    private function render_select_field( string $name, string $label, string $value, array $options, bool $required = false, array $wrapper_attr = [], array $select_attr = [], string $description = '' ) : void {
        echo '<div' . $this->format_wrapper_attributes( $wrapper_attr ) . '>';
        echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
        $attributes = array_merge(
            [
                'id'   => $name,
                'name' => $name,
            ],
            $select_attr
        );

        if ( $required && empty( $attributes['required'] ) ) {
            $attributes['required'] = 'required';
        }

        echo '<select' . $this->format_attributes( $attributes ) . '>';
        echo '<option value="">' . esc_html__( 'Wybierz…', 'estate-office' ) . '</option>';
        foreach ( $options as $option_value => $option_label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $option_value ), selected( $value, $option_value, false ), esc_html( $option_label ) );
        }
        echo '</select>';
        if ( '' !== $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Renderuje pole tylko do odczytu.
     *
     * @param string              $name        Nazwa pola.
     * @param string              $label       Etykieta.
     * @param string              $value       Wartość.
     * @param array<string,mixed> $attributes  Atrybuty inputa.
     * @param string              $description Opis pola.
     *
     * @return void
     */
    private function render_readonly_field( string $name, string $label, string $value, array $attributes = [], string $description = '' ) : void {
        $attributes = array_merge(
            [
                'type'     => 'text',
                'id'       => $name,
                'name'     => $name,
                'readonly' => 'readonly',
            ],
            $attributes
        );

        if ( '' !== $value ) {
            $attributes['value'] = $value;
        }

        echo '<div' . $this->format_wrapper_attributes() . '>';
        echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
        echo '<input' . $this->format_attributes( $attributes ) . ' />';
        if ( '' !== $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Renderuje pojedynczy checkbox.
     *
     * @param string               $name         Nazwa.
     * @param string               $label        Etykieta.
     * @param bool|int             $checked      Czy zaznaczony.
     * @param array<string,string> $wrapper_attr Atrybuty kontenera.
     *
     * @return void
     */
    private function render_checkbox_field( string $name, string $label, $checked, array $wrapper_attr = [] ) : void {
        echo '<div' . $this->format_wrapper_attributes( $wrapper_attr ) . '>';
        printf( '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>', esc_attr( $name ), checked( (bool) $checked, true, false ), esc_html( $label ) );
        echo '</div>';
    }

    /**
     * Renderuje grupę checkboxów.
     *
     * @param string               $name         Nazwa pola.
     * @param string               $label        Etykieta grupy.
     * @param array<int,string>    $values       Wybrane wartości.
     * @param array<string,string> $options      Dostępne opcje.
     * @param array<string,string> $wrapper_attr Atrybuty kontenera.
     *
     * @return void
     */
    private function render_checkboxes_field( string $name, string $label, array $values, array $options, array $wrapper_attr = [] ) : void {
        echo '<div' . $this->format_wrapper_attributes( $wrapper_attr ) . '>';
        echo '<label>' . esc_html( $label ) . '</label>';
        echo '<div class="estate-office-checkboxes">';
        foreach ( $options as $option_value => $option_label ) {
            printf(
                '<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
                esc_attr( $name ),
                esc_attr( $option_value ),
                checked( in_array( $option_value, $values, true ), true, false ),
                esc_html( $option_label )
            );
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderuje sekcję powierzchni dodatkowej.
     *
     * @param string               $prefix      Prefiks pól.
     * @param string               $label       Etykieta sekcji.
     * @param array<string,mixed>  $data        Dane sekcji.
     * @param bool                 $with_qty    Czy uwzględniać ilość.
     *
     * @return void
     */
    private function render_additional_area_field( string $prefix, string $label, array $data, bool $with_qty = true ) : void {
        echo '<div' . $this->format_wrapper_attributes() . '>';
        echo '<label>' . esc_html( $label ) . '</label>';
        printf( '<label><input type="checkbox" name="%1$s_enabled" value="1" %2$s /> %3$s</label>', esc_attr( $prefix ), checked( ! empty( $data['enabled'] ), true, false ), esc_html__( 'Dodaj', 'estate-office' ) );

        if ( $with_qty ) {
            printf( '<input type="number" class="small-text" name="%1$s_count" value="%2$s" placeholder="%3$s" min="0" />', esc_attr( $prefix ), esc_attr( $data['count'] ?? '' ), esc_attr__( 'Liczba', 'estate-office' ) );
        }

        printf( '<input type="number" class="small-text" name="%1$s_area" value="%2$s" placeholder="%3$s" step="0.01" min="0" />', esc_attr( $prefix ), esc_attr( $data['area'] ?? '' ), esc_attr__( 'Pow. m²', 'estate-office' ) );
        echo '</div>';
    }

    /**
     * Renderuje mapę wyboru lokalizacji.
     *
     * @param array<string,mixed> $data Dane nieruchomości.
     *
     * @return void
     */
    private function render_map_field( array $data ) : void {
        $wrapper_attr = [
            'class'            => 'estate-office-property-map-wrapper',
            'data-map-wrapper' => '1',
        ];

        echo '<div' . $this->format_wrapper_attributes( $wrapper_attr ) . '>';
        echo '<label>' . esc_html__( 'Lokalizacja na mapie', 'estate-office' ) . '</label>';

        if ( '' !== $this->get_google_maps_api_key() ) {
            printf(
                '<div id="estate-office-property-map" class="estate-office-property-map" data-lat="%1$s" data-lng="%2$s" data-zoom="14"></div>',
                esc_attr( (string) $data['latitude'] ),
                esc_attr( (string) $data['longitude'] )
            );
            echo '<p class="description">' . esc_html__( 'Kliknij na mapie lub przeciągnij znacznik, aby ustawić współrzędne.', 'estate-office' ) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__( 'Dodaj klucz API Map Google w ustawieniach, aby aktywować mapę wyboru lokalizacji.', 'estate-office' ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Renderuje podgląd galerii zdjęć.
     *
     * @param array<int,int> $ids Lista ID załączników.
     *
     * @return void
     */
    private function render_gallery_preview( array $ids ) : void {
        $message     = __( 'Brak wybranych zdjęć. Dodaj je przyciskiem powyżej.', 'estate-office' );
        $classes     = 'estate-office-gallery-preview';
        $has_gallery = ! empty( $ids );

        if ( ! $has_gallery ) {
            $classes .= ' is-empty';
        }

        $attributes = [
            'class'      => $classes,
            'id'         => 'estate-office-gallery-preview',
            'data-empty' => $message,
        ];

        echo '<div' . $this->format_wrapper_attributes( $attributes ) . '>';

        if ( ! $has_gallery ) {
            echo '<p class="description">' . esc_html( $message ) . '</p>';
        } else {
            foreach ( $ids as $attachment_id ) {
                $attachment_id = (int) $attachment_id;
                $image         = wp_get_attachment_image( $attachment_id, 'thumbnail', false, [ 'loading' => 'lazy' ] );

                if ( ! $image ) {
                    continue;
                }

                $title = get_the_title( $attachment_id );

                echo '<figure>';
                echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zweryfikowane wyjście z WP.
                echo '<figcaption>' . esc_html( $title ? $title : '#' . $attachment_id ) . '</figcaption>';
                echo '</figure>';
            }
        }

        echo '</div>';
    }

    /**
     * Formatuje atrybuty kontenera pola.
     *
     * @param array<string,string> $attributes Atrybuty.
     *
     * @return string
     */
    private function format_wrapper_attributes( array $attributes = [] ) : string {
        $classes = 'field';
        if ( isset( $attributes['class'] ) ) {
            $classes .= ' ' . $attributes['class'];
            unset( $attributes['class'] );
        }

        $attributes = array_merge( [ 'class' => $classes ], $attributes );

        return $this->format_attributes( $attributes );
    }

    /**
     * Formatuje atrybuty HTML.
     *
     * @param array<string,mixed> $attributes Atrybuty.
     *
     * @return string
     */
    private function format_attributes( array $attributes ) : string {
        $output = '';

        foreach ( $attributes as $key => $value ) {
            if ( null === $value || '' === $key ) {
                continue;
            }

            $output .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
        }

        return $output;
    }

    /**
     * Zwraca klucz API Map Google.
     *
     * @return string
     */
    private function get_google_maps_api_key() : string {
        if ( null !== $this->google_maps_api_key ) {
            return $this->google_maps_api_key;
        }

        $settings = get_option( 'estate_office_settings', [] );
        if ( is_array( $settings ) && ! empty( $settings['google_maps_api_key'] ) ) {
            $this->google_maps_api_key = (string) $settings['google_maps_api_key'];
        } else {
            $this->google_maps_api_key = '';
        }

        return $this->google_maps_api_key;
    }

    /**
     * Dostępne typy transakcji.
     *
     * @return array<string,string>
     */
    private function get_transaction_types() : array {
        return [
            'SPRZEDAŻ' => __( 'Sprzedaż', 'estate-office' ),
            'KUPNO'    => __( 'Kupno', 'estate-office' ),
            'WYNAJEM'  => __( 'Wynajem', 'estate-office' ),
            'NAJEM'    => __( 'Najem', 'estate-office' ),
        ];
    }

    /**
     * Dostępne typy nieruchomości.
     *
     * @return array<string,string>
     */
    private function get_property_types() : array {
        return [
            'MIESZKANIE' => __( 'Mieszkanie', 'estate-office' ),
            'DOM'        => __( 'Dom', 'estate-office' ),
            'DZIAŁKA'    => __( 'Działka', 'estate-office' ),
            'LOKAL'      => __( 'Lokal handlowo-usługowy', 'estate-office' ),
        ];
    }

    /**
     * Dostępne typy domów.
     *
     * @return array<string,string>
     */
    private function get_house_types() : array {
        return [
            'WOLNOSTOJĄCY' => __( 'Wolnostojący', 'estate-office' ),
            'BLIŹNIAK'      => __( 'Bliźniak', 'estate-office' ),
            'SZEREGOWIEC'   => __( 'Szeregowiec', 'estate-office' ),
            'WIELORODZINNY' => __( 'Wielorodzinny', 'estate-office' ),
        ];
    }

    /**
     * Dostępne stany prawne.
     *
     * @return array<string,string>
     */
    private function get_ownership_status_options() : array {
        return [
            'WŁASNOŚĆ'                          => __( 'Własność', 'estate-office' ),
            'WSPÓŁWŁASNOŚĆ'                     => __( 'Współwłasność', 'estate-office' ),
            'SPÓŁDZIELCZE_WŁASNOŚCIOWE'         => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
            'DZIERŻAWA'                         => __( 'Dzierżawa', 'estate-office' ),
            'INNE'                              => __( 'Inne', 'estate-office' ),
        ];
    }

    /**
     * Dostępne waluty.
     *
     * @return array<string,string>
     */
    private function get_currency_options() : array {
        return [
            'PLN' => 'PLN',
            'EUR' => 'EUR',
            'USD' => 'USD',
        ];
    }

    /**
     * Dostępne kształty działki.
     *
     * @return array<string,string>
     */
    private function get_plot_shapes() : array {
        return [
            'REGULARNY'   => __( 'Regularny', 'estate-office' ),
            'NIEREGULARNY'=> __( 'Nieregularny', 'estate-office' ),
        ];
    }

    /**
     * Stany wykończenia.
     *
     * @return array<string,string>
     */
    private function get_finish_states() : array {
        return [
            'DO_WYKONCZENIA' => __( 'Do wykończenia', 'estate-office' ),
            'DO_REMONTU'     => __( 'Do remontu', 'estate-office' ),
            'DOBRY'          => __( 'Dobry', 'estate-office' ),
            'BARDZO_DOBRY'   => __( 'Bardzo dobry', 'estate-office' ),
            'DEWELOPERSKI'   => __( 'Stan deweloperski', 'estate-office' ),
        ];
    }

    /**
     * Ekspozycja okien.
     *
     * @return array<string,string>
     */
    private function get_exposure_options() : array {
        return [
            'PÓŁNOC' => __( 'Północ', 'estate-office' ),
            'POŁUDNIE' => __( 'Południe', 'estate-office' ),
            'WSCHÓD' => __( 'Wschód', 'estate-office' ),
            'ZACHÓD' => __( 'Zachód', 'estate-office' ),
        ];
    }

    /**
     * Widoki.
     *
     * @return array<string,string>
     */
    private function get_view_options() : array {
        return [
            'PANORAMA' => __( 'Panorama', 'estate-office' ),
            'PARK'     => __( 'Park', 'estate-office' ),
            'ULICA'    => __( 'Ulica', 'estate-office' ),
            'INNE'     => __( 'Inne', 'estate-office' ),
        ];
    }

    /**
     * Opcje rozkładu.
     *
     * @return array<string,string>
     */
    private function get_layout_options() : array {
        return [
            'ROZKLADOWE' => __( 'Rozkładowe', 'estate-office' ),
            'OTWARTE'    => __( 'Otwarte', 'estate-office' ),
            'DWUSTRONNE'=> __( 'Dwustronne', 'estate-office' ),
        ];
    }

    /**
     * Rodzaje kuchni.
     *
     * @return array<string,string>
     */
    private function get_kitchen_types() : array {
        return [
            'ANEKS'      => __( 'Aneks', 'estate-office' ),
            'ODDZIELNA'  => __( 'Oddzielna', 'estate-office' ),
            'Z_SALONEM'  => __( 'Połączona z salonem', 'estate-office' ),
        ];
    }

    /**
     * Typy miejsc parkingowych.
     *
     * @return array<string,string>
     */
    private function get_parking_types() : array {
        return [
            'NAZIEMNE'    => __( 'Miejsce naziemne', 'estate-office' ),
            'PODZIEMNE'   => __( 'Miejsce podziemne', 'estate-office' ),
            'GARAZ'       => __( 'Garaż', 'estate-office' ),
        ];
    }

    /**
     * Typy ogrzewania.
     *
     * @return array<string,string>
     */
    private function get_heating_types() : array {
        return [
            'MIEJSKIE'   => __( 'Miejskie', 'estate-office' ),
            'GAZOWE'     => __( 'Gazowe', 'estate-office' ),
            'ELEKTRYCZNE'=> __( 'Elektryczne', 'estate-office' ),
            'POMPA_CIEPLA' => __( 'Pompa ciepła', 'estate-office' ),
            'INNE'       => __( 'Inne', 'estate-office' ),
        ];
    }

    /**
     * Typy wody.
     *
     * @return array<string,string>
     */
    private function get_water_types() : array {
        return [
            'MIEJSKA' => __( 'Woda miejska', 'estate-office' ),
            'STUDNIA' => __( 'Studnia', 'estate-office' ),
            'INNE'    => __( 'Inne', 'estate-office' ),
        ];
    }

    /**
     * Typy kanalizacji.
     *
     * @return array<string,string>
     */
    private function get_sewage_types() : array {
        return [
            'MIEJSKA' => __( 'Kanalizacja miejska', 'estate-office' ),
            'SZAMBO'  => __( 'Szambo', 'estate-office' ),
            'PRZYDOMOWA' => __( 'Przydomowa oczyszczalnia', 'estate-office' ),
        ];
    }

    /**
     * Udogodnienia.
     *
     * @return array<string,string>
     */
    private function get_amenities_options() : array {
        return [
            'WINDA'        => __( 'Winda', 'estate-office' ),
            'KLIMATYZACJA' => __( 'Klimatyzacja', 'estate-office' ),
            'MONITORING'   => __( 'Monitoring / Ochrona', 'estate-office' ),
            'RECEPCJA'     => __( 'Recepcja', 'estate-office' ),
            'TEREN_ZAMK'   => __( 'Teren zamknięty', 'estate-office' ),
            'DOMOFON'      => __( 'Domofon / Wideodomofon', 'estate-office' ),
        ];
    }

    /**
     * Poziomy umeblowania.
     *
     * @return array<string,string>
     */
    private function get_equipment_levels() : array {
        return [
            'TAK'       => __( 'Tak', 'estate-office' ),
            'NIE'       => __( 'Nie', 'estate-office' ),
            'CZĘŚCIOWE' => __( 'Częściowe', 'estate-office' ),
        ];
    }

    /**
     * Wyposażenie.
     *
     * @return array<string,string>
     */
    private function get_equipment_items() : array {
        return [
            'PRALKA'    => __( 'Pralka', 'estate-office' ),
            'ZMYWARKA'  => __( 'Zmywarka', 'estate-office' ),
            'LODOWKA'   => __( 'Lodówka', 'estate-office' ),
            'KUCHENKA'  => __( 'Kuchenka', 'estate-office' ),
            'PIEKARNIK' => __( 'Piekarnik', 'estate-office' ),
            'TELEWIZOR' => __( 'Telewizor', 'estate-office' ),
            'MIKROFALA' => __( 'Mikrofalówka', 'estate-office' ),
        ];
    }
}
