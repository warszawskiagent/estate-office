<?php
/**
 * Public template for displaying agent profile pages.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $estate_office_agent_profile;

if ( empty( $estate_office_agent_profile['agent'] ) ) {
    wp_die( esc_html__( 'Profil agenta jest niedostępny.', 'estate-office' ) );
}

$agent     = $estate_office_agent_profile['agent'];
$relations = $estate_office_agent_profile['relations'] ?? [];

$contact_data = [];
if ( ! empty( $agent->contact_data ) ) {
    $decoded = json_decode( $agent->contact_data, true );
    if ( is_array( $decoded ) ) {
        $contact_data = $decoded;
    }
}

$name = EstateOffice_Admin_Agents::format_agent_name( $agent );
if ( '' === $name ) {
    $name = sprintf( __( 'Agent #%d', 'estate-office' ), (int) $agent->id );
}

$photo = '';
if ( ! empty( $agent->photo_id ) ) {
    $photo = wp_get_attachment_image( (int) $agent->photo_id, 'large', false, [ 'class' => 'estate-office-agent-photo-img' ] );
}

$public_offers = [];
if ( ! empty( $relations['properties'] ) && is_array( $relations['properties'] ) ) {
    $export_ids = [];
    foreach ( $relations['properties'] as $row ) {
        if ( empty( $row->export_www ) ) {
            continue;
        }

        $export_ids[] = (int) $row->id;
    }

    $cover_media = EstateOffice_Admin_Properties::get_cover_media_for_properties( $export_ids );

    foreach ( $relations['properties'] as $row ) {
        if ( empty( $row->export_www ) ) {
            continue;
        }

        $details       = $row->details ? json_decode( $row->details, true ) : [];
        $address_parts = $row->address ? json_decode( $row->address, true ) : [];
        $tags          = $row->tags ? json_decode( $row->tags, true ) : [];
        $address_label = is_array( $address_parts ) ? estate_office_format_address( $address_parts ) : '';

        $tag_labels = [];
        if ( is_array( $tags ) ) {
            $tag_map = [
                'new_offer' => __( 'Nowa oferta', 'estate-office' ),
                'exclusive' => __( 'Wyłączność', 'estate-office' ),
                'new_price' => __( 'Nowa cena', 'estate-office' ),
                'premium'   => __( 'Premium', 'estate-office' ),
            ];

            foreach ( $tags as $key => $value ) {
                if ( ! isset( $tag_map[ $key ] ) ) {
                    continue;
                }

                $active = false;
                if ( is_array( $value ) ) {
                    $active = ! empty( $value['active'] );
                } else {
                    $active = ! empty( $value );
                }

                if ( $active ) {
                    $tag_labels[] = $tag_map[ $key ];
                }
            }
        }

        $media      = $cover_media[ (int) $row->id ] ?? [];
        $image_id   = isset( $media['watermarked_id'] ) && $media['watermarked_id'] ? (int) $media['watermarked_id'] : (int) ( $media['attachment_id'] ?? 0 );
        $image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
        $image_alt  = $address_label ?: sprintf( '%s · %s', $row->transaction_type, $row->property_type );

        $public_offers[] = [
            'id'          => (int) $row->id,
            'transaction' => $row->transaction_type,
            'type'        => $row->property_type,
            'address'     => $address_label,
            'details'     => $details,
            'tags'        => $tag_labels,
            'image_url'   => $image_url,
            'image_id'    => $image_id,
            'image_alt'   => $image_alt,
        ];
    }
}

$stats = [
    __( 'Umowy w CRM', 'estate-office' )        => ! empty( $relations['contracts'] ) ? count( (array) $relations['contracts'] ) : 0,
    __( 'Oferty w CRM', 'estate-office' )        => ! empty( $relations['properties'] ) ? count( (array) $relations['properties'] ) : 0,
    __( 'Aktywni klienci', 'estate-office' )     => ! empty( $relations['clients'] ) ? count( (array) $relations['clients'] ) : 0,
];

$contact_items = [];
if ( ! empty( $agent->phone ) ) {
    $contact_items[] = [
        'label' => __( 'Telefon', 'estate-office' ),
        'value' => $agent->phone,
        'url'   => 'tel:' . preg_replace( '/[^0-9+]/', '', $agent->phone ),
    ];
}
if ( ! empty( $agent->email ) ) {
    $contact_items[] = [
        'label' => __( 'E-mail', 'estate-office' ),
        'value' => $agent->email,
        'url'   => 'mailto:' . sanitize_email( $agent->email ),
    ];
}
if ( ! empty( $contact_data['phone_alt'] ) ) {
    $contact_items[] = [
        'label' => __( 'Telefon dodatkowy', 'estate-office' ),
        'value' => $contact_data['phone_alt'],
        'url'   => 'tel:' . preg_replace( '/[^0-9+]/', '', $contact_data['phone_alt'] ),
    ];
}
if ( ! empty( $contact_data['email_alt'] ) ) {
    $contact_items[] = [
        'label' => __( 'E-mail dodatkowy', 'estate-office' ),
        'value' => $contact_data['email_alt'],
        'url'   => 'mailto:' . sanitize_email( $contact_data['email_alt'] ),
    ];
}
if ( ! empty( $contact_data['linkedin'] ) ) {
    $contact_items[] = [
        'label' => __( 'LinkedIn', 'estate-office' ),
        'value' => esc_url( $contact_data['linkedin'] ),
        'url'   => esc_url( $contact_data['linkedin'] ),
        'is_external' => true,
    ];
}
if ( ! empty( $contact_data['facebook'] ) ) {
    $contact_items[] = [
        'label' => __( 'Facebook', 'estate-office' ),
        'value' => esc_url( $contact_data['facebook'] ),
        'url'   => esc_url( $contact_data['facebook'] ),
        'is_external' => true,
    ];
}

$crm_url = estate_office_get_crm_page_url();

?><main class="estate-office-agent-profile" aria-labelledby="estate-office-agent-title">
    <div class="estate-office-agent-brand">
        <?php echo estate_office_get_brand_badge_html( 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <header class="estate-office-agent-header">
        <?php if ( $photo ) : ?>
            <figure class="estate-office-agent-photo"><?php echo $photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure>
        <?php endif; ?>
        <div class="estate-office-agent-summary">
            <h1 id="estate-office-agent-title"><?php echo esc_html( $name ); ?></h1>
            <?php if ( ! empty( $contact_items ) ) : ?>
                <ul class="estate-office-agent-contact">
                    <?php foreach ( $contact_items as $item ) :
                        $label = esc_html( $item['label'] );
                        $value = esc_html( $item['value'] );
                        ?>
                        <li>
                            <span><?php echo $label; ?></span>
                            <?php if ( ! empty( $item['url'] ) ) : ?>
                                <a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo ! empty( $item['is_external'] ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo $value; ?></a>
                            <?php else : ?>
                                <span><?php echo $value; ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ( array_sum( $stats ) > 0 ) : ?>
                <dl class="estate-office-agent-stats">
                    <?php foreach ( $stats as $label => $value ) : ?>
                        <?php if ( $value > 0 ) : ?>
                            <div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( (string) $value ); ?></dd></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </header>

    <?php if ( ! empty( $agent->description ) ) : ?>
        <section class="estate-office-agent-about">
            <h2><?php esc_html_e( 'O agencie', 'estate-office' ); ?></h2>
            <div class="estate-office-agent-content"><?php echo wp_kses_post( wpautop( $agent->description ) ); ?></div>
        </section>
    <?php endif; ?>

    <?php if ( ! empty( $public_offers ) ) : ?>
        <section class="estate-office-agent-offers">
            <h2><?php esc_html_e( 'Oferty tego agenta', 'estate-office' ); ?></h2>
            <div class="estate-office-agent-offers-grid">
                <?php foreach ( $public_offers as $offer ) :
                    $details = $offer['details'];
                    ?>
                    <article class="estate-office-agent-offer">
                        <?php if ( ! empty( $offer['image_url'] ) ) : ?>
                            <figure class="estate-office-offer-image">
                                <img src="<?php echo esc_url( $offer['image_url'] ); ?>" alt="<?php echo esc_attr( $offer['image_alt'] ); ?>" loading="lazy" />
                            </figure>
                        <?php endif; ?>
                        <header>
                            <span class="estate-office-offer-number"><?php echo esc_html( sprintf( '#%05d', $offer['id'] ) ); ?></span>
                            <?php if ( ! empty( $offer['tags'] ) ) : ?>
                                <ul class="estate-office-offer-tags">
                                    <?php foreach ( $offer['tags'] as $tag ) : ?>
                                        <li><?php echo esc_html( $tag ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </header>
                        <p class="estate-office-offer-address"><?php echo esc_html( $offer['address'] ); ?></p>
                        <p class="estate-office-offer-type"><?php echo esc_html( sprintf( '%s · %s', $offer['transaction'], $offer['type'] ) ); ?></p>
                        <?php if ( isset( $details['price'] ) ) : ?>
                            <p class="estate-office-offer-price"><?php echo esc_html( number_format_i18n( (float) $details['price'], 2 ) . ' PLN' ); ?></p>
                        <?php endif; ?>
                        <ul class="estate-office-offer-meta">
                            <?php if ( ! empty( $details['area'] ) ) : ?>
                                <li><?php echo esc_html( $details['area'] . ' m²' ); ?></li>
                            <?php endif; ?>
                            <?php if ( ! empty( $details['rooms'] ) ) : ?>
                                <li><?php echo esc_html( sprintf( _n( '%d pokój', '%d pokoje', (int) $details['rooms'], 'estate-office' ), (int) $details['rooms'] ) ); ?></li>
                            <?php endif; ?>
                            <?php if ( ! empty( $details['price_m2'] ) ) : ?>
                                <li><?php echo esc_html( sprintf( __( '%s / m²', 'estate-office' ), number_format_i18n( (float) $details['price_m2'], 2 ) . ' PLN' ) ); ?></li>
                            <?php endif; ?>
                        </ul>
                        <a class="estate-office-button tertiary" href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'properties', 'eo_property' => $offer['id'] ], $crm_url ) ); ?>"><?php esc_html_e( 'Zobacz w CRM', 'estate-office' ); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
