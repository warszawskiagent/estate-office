<?php
/**
 * Shared helper functions for EstateOffice CRM.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'estate_office_get_agent_base_slug' ) ) {
    /**
     * Retrieve the base slug used for public agent pages.
     */
    function estate_office_get_agent_base_slug(): string {
        $base = get_option( 'estate_office_agent_slug_base', 'agenci' );
        $base = sanitize_title( $base );

        return '' !== $base ? $base : 'agenci';
    }
}

if ( ! function_exists( 'estate_office_generate_agent_slug' ) ) {
    /**
     * Generate a unique slug for an agent.
     *
     * @param string $requested Requested slug.
     * @param string $first     First name.
     * @param string $last      Last name.
     * @param int    $agent_id  Agent identifier (for updates).
     */
    function estate_office_generate_agent_slug( string $requested, string $first, string $last, int $agent_id = 0 ): string {
        global $wpdb;

        $base = sanitize_title( $requested );
        if ( '' === $base ) {
            $base = sanitize_title( trim( $first . ' ' . $last ) );
        }

        if ( '' === $base ) {
            $base = 'agent';
        }

        // Keep margin for numeric suffixes.
        $base = substr( $base, 0, 190 );

        $table = $wpdb->prefix . 'eo_agents';
        $slug  = $base;
        $i     = 2;

        while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1", $slug, $agent_id ) ) ) {
            $suffix = '-' . $i;
            $slug   = substr( $base, 0, 200 - strlen( $suffix ) ) . $suffix;
            $i++;
        }

        return substr( $slug, 0, 200 );
    }
}

if ( ! function_exists( 'estate_office_get_agent_url' ) ) {
    /**
     * Build public URL for an agent slug.
     */
    function estate_office_get_agent_url( string $slug ): string {
        $slug = sanitize_title( $slug );
        if ( '' === $slug ) {
            return '';
        }

        $base = estate_office_get_agent_base_slug();

        return trailingslashit( home_url( trailingslashit( $base ) . $slug ) );
    }
}

if ( ! function_exists( 'estate_office_format_address' ) ) {
    /**
     * Compose human readable address from parts.
     *
     * @param array<string,mixed> $address Address map.
     */
    function estate_office_format_address( array $address ): string {
        if ( empty( $address ) ) {
            return '';
        }

        $parts = [];

        if ( ! empty( $address['street'] ) ) {
            $street = $address['street'];
            if ( ! empty( $address['number'] ) ) {
                $street .= ' ' . $address['number'];
            }
            if ( ! empty( $address['unit'] ) ) {
                $street .= '/' . $address['unit'];
            }
            $parts[] = $street;
        }

        if ( ! empty( $address['postal_code'] ) ) {
            $parts[] = $address['postal_code'];
        }

        if ( ! empty( $address['district'] ) ) {
            $parts[] = $address['district'];
        }

        if ( ! empty( $address['city'] ) ) {
            $parts[] = $address['city'];
        }

        if ( ! empty( $address['country'] ) ) {
            $parts[] = $address['country'];
        }

        return implode( ', ', $parts );
    }
}

if ( ! function_exists( 'estate_office_get_crm_page_url' ) ) {
    /**
     * Return permalink to CRM page if available.
     */
    function estate_office_get_crm_page_url(): string {
        $page_id = (int) get_option( 'estate_office_crm_page_id' );
        if ( $page_id ) {
            $link = get_permalink( $page_id );
            if ( $link ) {
                return $link;
            }
        }

        return home_url();
    }
}

if ( ! function_exists( 'estate_office_get_property_types' ) ) {
    /**
     * Provide canonical list of property types used across the plugin.
     *
     * @return array<int,string>
     */
    function estate_office_get_property_types(): array {
        return [ 'MIESZKANIE', 'DOM', 'DZIAŁKA', 'LOKAL H/U' ];
    }
}

if ( ! function_exists( 'estate_office_locate_template' ) ) {
    /**
     * Locate template file that can be overridden in the active theme.
     */
    function estate_office_locate_template( string $slug ): string {
        $slug      = ltrim( $slug, '/' );
        $templates = [ 'estate-office/' . $slug, $slug ];

        $located = '';
        if ( function_exists( 'locate_template' ) ) {
            $located = locate_template( $templates, false, false );
        }

        if ( $located && file_exists( $located ) ) {
            return $located;
        }

        $default = ESTATE_OFFICE_PATH . 'templates/' . $slug;
        return file_exists( $default ) ? $default : '';
    }
}

if ( ! function_exists( 'estate_office_render_template' ) ) {
    /**
     * Render template file with provided context and return HTML string.
     *
     * @param string               $slug    Template slug relative to templates directory.
     * @param array<string,mixed>  $context Variables available inside template.
     */
    function estate_office_render_template( string $slug, array $context = [] ): string {
        $template = estate_office_locate_template( $slug );
        if ( ! $template ) {
            return '';
        }

        ob_start();
        if ( ! empty( $context ) ) {
            extract( $context, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }
        include $template;
        return ob_get_clean();
    }
}

if ( ! function_exists( 'estate_office_output_template' ) ) {
    /**
     * Echo template output.
     *
     * @param string              $slug    Template slug.
     * @param array<string,mixed> $context Template context.
     */
    function estate_office_output_template( string $slug, array $context = [] ): void {
        echo estate_office_render_template( $slug, $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if ( ! function_exists( 'estate_office_get_office_logo_attachment_id' ) ) {
    /**
     * Retrieve attachment identifier for configured office logo.
     */
    function estate_office_get_office_logo_attachment_id(): int {
        $attachment_id = (int) get_option( 'estate_office_office_logo_attachment', 0 );
        if ( ! $attachment_id ) {
            return 0;
        }

        return estate_office_is_attachment_available( $attachment_id ) ? $attachment_id : 0;
    }
}

if ( ! function_exists( 'estate_office_get_office_branding' ) ) {
    /**
     * Build branding payload for the current office.
     *
     * @return array{url:string,alt:string,initials:string,name:string}
     */
    function estate_office_get_office_branding(): array {
        $site_name = get_bloginfo( 'name' );
        $brand_name = $site_name ? $site_name : __( 'Biuro nieruchomości', 'estate-office' );

        $initials = '';
        $words    = preg_split( '/[\s\-]+/u', wp_strip_all_tags( $brand_name ) );
        if ( $words && is_array( $words ) ) {
            foreach ( $words as $word ) {
                $word = trim( $word );
                if ( '' === $word ) {
                    continue;
                }

                $letter = function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
                if ( $letter ) {
                    $initials .= function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter ) : strtoupper( $letter );
                }

                if ( strlen( $initials ) >= 3 ) {
                    break;
                }
            }
        }

        if ( '' === $initials ) {
            $initials = 'EO';
        }

        $logo_id = estate_office_get_office_logo_attachment_id();
        $logo    = '';
        $alt     = $brand_name;

        if ( $logo_id ) {
            $logo = wp_get_attachment_image_url( $logo_id, 'medium' );
            $alt  = get_post_meta( $logo_id, '_wp_attachment_image_alt', true );
            if ( ! $alt ) {
                $alt = $brand_name;
            }
        }

        return [
            'url'       => $logo ? $logo : '',
            'alt'       => $alt,
            'initials'  => $initials,
            'name'      => $brand_name,
        ];
    }
}

if ( ! function_exists( 'estate_office_get_brand_badge_html' ) ) {
    /**
     * Render reusable branding badge.
     */
    function estate_office_get_brand_badge_html( string $context = 'admin' ): string {
        $branding = estate_office_get_office_branding();
        $context  = 'public' === $context ? 'public' : 'admin';

        $classes = [ 'estate-office-brand-badge', 'estate-office-brand-badge--' . $context ];

        ob_start();
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
            <?php if ( $branding['url'] ) : ?>
                <img src="<?php echo esc_url( $branding['url'] ); ?>" alt="<?php echo esc_attr( $branding['alt'] ); ?>" class="estate-office-brand-logo" loading="lazy" />
            <?php else : ?>
                <span class="estate-office-brand-placeholder" aria-hidden="true"><?php echo esc_html( $branding['initials'] ); ?></span>
            <?php endif; ?>
            <span class="estate-office-brand-name"><?php echo esc_html( $branding['name'] ); ?></span>
        </div>
        <?php
        return trim( (string) ob_get_clean() );
    }
}

if ( ! function_exists( 'estate_office_get_watermark_attachment_id' ) ) {
    /**
     * Get configured watermark attachment when valid.
     */
    function estate_office_get_watermark_attachment_id(): int {
        $attachment_id = (int) get_option( 'estate_office_watermark_attachment', 0 );
        if ( ! $attachment_id ) {
            return 0;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return 0;
        }

        return $attachment_id;
    }
}

if ( ! function_exists( 'estate_office_is_attachment_available' ) ) {
    /**
     * Check whether attachment physical file exists.
     */
    function estate_office_is_attachment_available( int $attachment_id ): bool {
        if ( $attachment_id <= 0 ) {
            return false;
        }

        $file = get_attached_file( $attachment_id );

        return (bool) ( $file && file_exists( $file ) );
    }
}

if ( ! function_exists( 'estate_office_get_watermark_signature' ) ) {
    /**
     * Build signature string for a watermark asset.
     */
    function estate_office_get_watermark_signature( int $watermark_id, string $watermark_path ): string {
        $parts = [ (string) $watermark_id ];
        if ( file_exists( $watermark_path ) ) {
            $parts[] = (string) filesize( $watermark_path );
            $parts[] = (string) filemtime( $watermark_path );
        }

        return md5( implode( ':', $parts ) );
    }
}

if ( ! function_exists( 'estate_office_apply_watermark_to_image' ) ) {
    /**
     * Apply watermark graphic to an image and save to destination.
     *
     * @return array|WP_Error
     */
    function estate_office_apply_watermark_to_image( string $source_path, string $watermark_path, string $destination ) {
        $editor = wp_get_image_editor( $source_path );
        if ( is_wp_error( $editor ) ) {
            return $editor;
        }

        $watermark = wp_get_image_editor( $watermark_path );
        if ( is_wp_error( $watermark ) ) {
            return $watermark;
        }

        if ( $editor instanceof WP_Image_Editor_GD && $watermark instanceof WP_Image_Editor_GD ) {
            $image_resource     = $editor->get_image();
            $watermark_resource = $watermark->get_image();

            if ( ! $image_resource || ! $watermark_resource ) {
                return new WP_Error( 'estate_office_watermark_preparation', __( 'Nie udało się przygotować znaku wodnego.', 'estate-office' ) );
            }

            $image_width  = imagesx( $image_resource );
            $image_height = imagesy( $image_resource );
            $wm_width     = imagesx( $watermark_resource );
            $wm_height    = imagesy( $watermark_resource );

            if ( $wm_width <= 0 || $wm_height <= 0 ) {
                return new WP_Error( 'estate_office_watermark_empty', __( 'Nie udało się przygotować znaku wodnego.', 'estate-office' ) );
            }

            $scale     = min( 1, ( $image_width * 0.3 ) / max( 1, $wm_width ) );
            $resampled = $watermark_resource;

            if ( $scale > 0 && $scale < 0.999 ) {
                $new_width  = max( 1, (int) round( $wm_width * $scale ) );
                $new_height = max( 1, (int) round( $wm_height * $scale ) );
                $resampled  = imagecreatetruecolor( $new_width, $new_height );
                imagealphablending( $resampled, false );
                imagesavealpha( $resampled, true );
                imagecopyresampled( $resampled, $watermark_resource, 0, 0, 0, 0, $new_width, $new_height, $wm_width, $wm_height );
                $wm_width  = $new_width;
                $wm_height = $new_height;
            }

            $margin = max( 10, (int) round( min( $image_width, $image_height ) * 0.02 ) );
            $x      = max( 0, $image_width - $wm_width - $margin );
            $y      = max( 0, $image_height - $wm_height - $margin );

            imagealphablending( $image_resource, true );
            imagealphablending( $resampled, true );
            imagesavealpha( $image_resource, true );
            imagesavealpha( $resampled, true );

            imagecopy( $image_resource, $resampled, $x, $y, 0, 0, $wm_width, $wm_height );

            $result = $editor->save( $destination );

            if ( $resampled !== $watermark_resource ) {
                imagedestroy( $resampled );
            }

            return $result;
        }

        if ( $editor instanceof WP_Image_Editor_Imagick && $watermark instanceof WP_Image_Editor_Imagick ) {
            $image   = $editor->get_image();
            $overlay = $watermark->get_image();

            if ( ! $image || ! $overlay ) {
                return new WP_Error( 'estate_office_watermark_preparation', __( 'Nie udało się przygotować znaku wodnego.', 'estate-office' ) );
            }

            $image_width  = $image->getImageWidth();
            $image_height = $image->getImageHeight();
            $overlay_w    = $overlay->getImageWidth();
            $overlay_h    = $overlay->getImageHeight();

            $scale = min( 1, ( $image_width * 0.3 ) / max( 1, $overlay_w ) );
            if ( $scale > 0 && $scale < 0.999 ) {
                $overlay->resizeImage(
                    max( 1, (int) round( $overlay_w * $scale ) ),
                    max( 1, (int) round( $overlay_h * $scale ) ),
                    Imagick::FILTER_LANCZOS,
                    1,
                    true
                );
                $overlay_w = $overlay->getImageWidth();
                $overlay_h = $overlay->getImageHeight();
            }

            $margin = max( 10, (int) round( min( $image_width, $image_height ) * 0.02 ) );
            $x      = max( 0, $image_width - $overlay_w - $margin );
            $y      = max( 0, $image_height - $overlay_h - $margin );

            $image->compositeImage( $overlay, Imagick::COMPOSITE_OVER, $x, $y );

            return $editor->save( $destination );
        }

        return new WP_Error( 'estate_office_watermark_support', __( 'Środowisko nie wspiera nakładania znaków wodnych.', 'estate-office' ) );
    }
}

if ( ! function_exists( 'estate_office_get_transaction_label' ) ) {
    /**
     * Provide human readable label for transaction type.
     */
    function estate_office_get_transaction_label( string $transaction, bool $lowercase = false ): string {
        $map = [
            'SPRZEDAŻ' => __( 'Sprzedaż', 'estate-office' ),
            'KUPNO'    => __( 'Kupno', 'estate-office' ),
            'WYNAJEM'  => __( 'Wynajem', 'estate-office' ),
            'NAJEM'    => __( 'Najem', 'estate-office' ),
        ];

        $label = $map[ strtoupper( $transaction ) ] ?? $transaction;

        if ( $lowercase ) {
            return function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
        }

        return $label;
    }
}

if ( ! function_exists( 'estate_office_generate_offer_slug' ) ) {
    /**
     * Generate slug for exported offer page.
     *
     * @param int                   $property_id Property identifier.
     * @param array<string,mixed>   $address     Address parts.
     */
    function estate_office_generate_offer_slug( int $property_id, array $address ): string {
        $parts = [ 'oferta', $property_id ];

        if ( ! empty( $address['city'] ) ) {
            $parts[] = $address['city'];
        }

        if ( ! empty( $address['street'] ) ) {
            $parts[] = $address['street'];
        }

        $slug = sanitize_title( implode( '-', $parts ) );

        return '' !== $slug ? $slug : 'oferta-' . $property_id;
    }
}

if ( ! function_exists( 'estate_office_assign_offer_terms' ) ) {
    /**
     * Ensure hierarchical offer taxonomy terms exist and assign them to page.
     *
     * @param int                   $page_id      Page identifier.
     * @param string                $transaction  Transaction type.
     * @param string                $property_type Property type.
     * @param array<string,mixed>   $address      Address parts.
     */
    function estate_office_assign_offer_terms( int $page_id, string $transaction, string $property_type, array $address ): void {
        $taxonomy = 'estate_office_offer_category';
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return;
        }

        $levels = [];
        $transaction_label = estate_office_get_transaction_label( $transaction );
        if ( '' !== $transaction_label ) {
            $levels[] = [
                'name' => $transaction_label,
                'slug' => 'eo-transaction-' . sanitize_title( $transaction ),
            ];
        }

        if ( '' !== $property_type ) {
            $levels[] = [
                'name' => $property_type,
                'slug' => 'eo-' . sanitize_title( $transaction ) . '-type-' . sanitize_title( $property_type ),
            ];
        }

        if ( ! empty( $address['city'] ) ) {
            $levels[] = [
                'name' => $address['city'],
                'slug' => 'eo-' . sanitize_title( $transaction ) . '-city-' . sanitize_title( $address['city'] ),
            ];
        }

        if ( ! empty( $address['district'] ) ) {
            $levels[] = [
                'name' => $address['district'],
                'slug' => 'eo-' . sanitize_title( $transaction ) . '-district-' . sanitize_title( $address['city'] . '-' . $address['district'] ),
            ];
        }

        if ( empty( $levels ) ) {
            wp_set_object_terms( $page_id, [], $taxonomy, false );
            return;
        }

        $parent   = 0;
        $term_ids = [];

        foreach ( $levels as $level ) {
            $slug = substr( sanitize_title( $level['slug'] ), 0, 190 );
            if ( '' === $slug ) {
                $slug = sanitize_title( $level['name'] );
            }

            $term = get_term_by( 'slug', $slug, $taxonomy );
            if ( ! $term ) {
                $created = wp_insert_term(
                    $level['name'],
                    $taxonomy,
                    [
                        'slug'   => $slug,
                        'parent' => $parent,
                    ]
                );

                if ( is_wp_error( $created ) ) {
                    continue;
                }

                $term_id = (int) $created['term_id'];
            } else {
                $term_id = (int) $term->term_id;
                if ( $term->parent !== $parent ) {
                    wp_update_term( $term_id, $taxonomy, [ 'parent' => $parent ] );
                }
            }

            $term_ids[] = $term_id;
            $parent      = $term_id;
        }

        if ( ! empty( $term_ids ) ) {
            wp_set_object_terms( $page_id, $term_ids, $taxonomy, false );
        }
    }
}

if ( ! function_exists( 'estate_office_map_offer_tag_labels' ) ) {
    /**
     * Map stored offer tags to display labels.
     *
     * @param array<string,mixed> $tags Raw tag payload.
     * @return array<int,string>
     */
    function estate_office_map_offer_tag_labels( array $tags ): array {
        if ( empty( $tags ) ) {
            return [];
        }

        $map = [
            'new_offer'     => __( 'Nowa oferta', 'estate-office' ),
            'exclusive'     => __( 'Wyłączność', 'estate-office' ),
            'new_price'     => __( 'Nowa cena', 'estate-office' ),
            'no_commission' => __( 'Bez prowizji', 'estate-office' ),
            'mls'           => __( 'Oferta MLS', 'estate-office' ),
            'premium'       => __( 'Premium', 'estate-office' ),
            'sold'          => __( 'Sprzedane', 'estate-office' ),
            'rented'        => __( 'Wynajęte', 'estate-office' ),
        ];

        $labels = [];
        foreach ( $map as $key => $label ) {
            if ( ! array_key_exists( $key, $tags ) ) {
                continue;
            }

            $value = $tags[ $key ];
            $active = false;

            if ( is_array( $value ) ) {
                if ( array_key_exists( 'active', $value ) ) {
                    $active = (bool) $value['active'];
                } else {
                    $active = ! empty( $value );
                }
            } else {
                $active = ! empty( $value );
            }

            if ( $active ) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}

if ( ! function_exists( 'estate_office_sync_property_page' ) ) {
    /**
     * Synchronize exported property with WordPress page.
     *
     * @param int         $property_id   Property identifier.
     * @param bool        $should_export Whether offer should be visible publicly.
     * @param object|null $property      Optional pre-fetched row.
     */
    function estate_office_sync_property_page( int $property_id, bool $should_export, $property = null ): void {
        if ( ! $property ) {
            $property = EstateOffice_Admin_Properties::get_property( $property_id );
        }

        if ( ! $property ) {
            return;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'eo_properties';
        $page_id = (int) ( $property->export_page_id ?? 0 );

        if ( ! $should_export ) {
            if ( $page_id && get_post( $page_id ) ) {
                wp_update_post(
                    [
                        'ID'          => $page_id,
                        'post_status' => 'draft',
                    ]
                );
                wp_set_object_terms( $page_id, [], 'estate_office_offer_category', false );
                delete_post_meta( $page_id, '_estate_office_property_id' );
                delete_post_thumbnail( $page_id );
            }

            $wpdb->update( $table, [ 'export_page_id' => null ], [ 'id' => $property_id ], [ '%d' ], [ '%d' ] );
            return;
        }

        $address = $property->address ? json_decode( $property->address, true ) : [];
        $details = $property->details ? json_decode( $property->details, true ) : [];

        $title_parts = [];
        if ( ! empty( $property->property_type ) ) {
            $title_parts[] = $property->property_type;
        }

        $transaction_label = estate_office_get_transaction_label( (string) $property->transaction_type, true );
        if ( '' !== $transaction_label ) {
            $title_parts[] = $transaction_label;
        }

        if ( ! empty( $address['city'] ) ) {
            $title_parts[] = $address['city'];
        }

        $title = trim( implode( ' – ', $title_parts ) );
        if ( '' === $title ) {
            $title = sprintf( __( 'Oferta #%d', 'estate-office' ), $property_id );
        }

        $content = sprintf( '[estate_office_offer id="%d"]', $property_id );
        $slug    = estate_office_generate_offer_slug( $property_id, $address );

        if ( $page_id && ! get_post( $page_id ) ) {
            $page_id = 0;
        }

        $page_args = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'meta_input'   => [
                '_estate_office_property_id' => $property_id,
            ],
        ];

        if ( $page_id ) {
            $page_args['ID'] = $page_id;
            $page_id         = wp_update_post( $page_args, true );
        } else {
            $page_args['post_name'] = $slug;
            $page_id                = wp_insert_post( $page_args, true );
        }

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return;
        }

        $wpdb->update(
            $table,
            [ 'export_page_id' => (int) $page_id ],
            [ 'id' => $property_id ],
            [ '%d' ],
            [ '%d' ]
        );

        $cover_media = EstateOffice_Admin_Properties::get_property_cover_media( $property_id );
        $image_id    = (int) ( $cover_media['watermarked_id'] ?? 0 );
        if ( ! $image_id ) {
            $image_id = (int) ( $cover_media['attachment_id'] ?? 0 );
        }

        if ( $image_id && estate_office_is_attachment_available( $image_id ) ) {
            set_post_thumbnail( $page_id, $image_id );
        }

        estate_office_assign_offer_terms( $page_id, (string) $property->transaction_type, (string) $property->property_type, $address );

        /**
         * Allow 3rd parties to hook after offer page synchronization.
         */
        do_action( 'estate_office_after_offer_sync', $page_id, $property_id, $details, $address );
    }
}

if ( ! function_exists( 'estate_office_get_property_page_url' ) ) {
    /**
     * Retrieve permalink for exported property.
     */
    function estate_office_get_property_page_url( int $property_id ): string {
        global $wpdb;
        $table   = $wpdb->prefix . 'eo_properties';
        $page_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT export_page_id FROM {$table} WHERE id = %d", $property_id ) );
        if ( ! $page_id ) {
            return '';
        }

        $link = get_permalink( $page_id );

        return $link ?: '';
    }
}

if ( ! function_exists( 'estate_office_create_watermarked_attachment' ) ) {
    /**
     * Generate a watermarked attachment for a given image.
     */
    function estate_office_create_watermarked_attachment( int $attachment_id, int $watermark_id, string $hash ): int {
        $source_path    = get_attached_file( $attachment_id );
        $watermark_path = get_attached_file( $watermark_id );

        if ( ! $source_path || ! file_exists( $source_path ) || ! $watermark_path || ! file_exists( $watermark_path ) ) {
            return 0;
        }

        $uploads = wp_upload_dir();
        if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
            return 0;
        }

        $directory = trailingslashit( $uploads['basedir'] ) . 'estate-office/watermarked';
        if ( ! wp_mkdir_p( $directory ) ) {
            return 0;
        }

        $extension = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
        if ( ! $extension ) {
            $extension = 'jpg';
        }

        $filename    = pathinfo( $source_path, PATHINFO_FILENAME );
        $target_file = sprintf( '%s-%s.%s', $filename, $hash, $extension );
        $destination = trailingslashit( $directory ) . $target_file;
        $result      = estate_office_apply_watermark_to_image( $source_path, $watermark_path, $destination );

        if ( is_wp_error( $result ) ) {
            return 0;
        }

        $mime_type = $result['mime-type'] ?? '';
        if ( ! $mime_type ) {
            $filetype  = wp_check_filetype( $target_file );
            $mime_type = $filetype['type'] ?? get_post_mime_type( $attachment_id );
        }

        $title        = get_the_title( $attachment_id );
        $attachment   = [
            'post_title'     => $title ? $title . ' – ' . __( 'znak wodny', 'estate-office' ) : basename( $target_file ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $attachment_id,
            'post_mime_type' => $mime_type,
            'guid'           => trailingslashit( $uploads['baseurl'] ) . 'estate-office/watermarked/' . $target_file,
        ];

        $watermarked_id = wp_insert_attachment( $attachment, $destination );
        if ( ! $watermarked_id || is_wp_error( $watermarked_id ) ) {
            @unlink( $destination );
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $watermarked_id, $destination );
        if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
            wp_update_attachment_metadata( $watermarked_id, $metadata );
        }

        update_post_meta( $watermarked_id, '_estate_office_source_attachment', $attachment_id );
        update_post_meta( $watermarked_id, '_estate_office_watermark_source', $watermark_id );
        update_post_meta( $watermarked_id, '_estate_office_watermark_hash', $hash );

        return (int) $watermarked_id;
    }
}

if ( ! function_exists( 'estate_office_ensure_watermarked_attachment' ) ) {
    /**
     * Ensure a watermarked copy exists and return its attachment identifier.
     */
    function estate_office_ensure_watermarked_attachment( int $attachment_id, ?int $watermark_id = null ): int {
        if ( $attachment_id <= 0 ) {
            return 0;
        }

        if ( null === $watermark_id ) {
            $watermark_id = estate_office_get_watermark_attachment_id();
        }

        if ( ! $watermark_id ) {
            return 0;
        }

        $watermark_path = get_attached_file( $watermark_id );
        if ( ! $watermark_path || ! file_exists( $watermark_path ) ) {
            return 0;
        }

        $hash     = estate_office_get_watermark_signature( $watermark_id, $watermark_path );
        $meta_key = '_estate_office_watermark_' . $hash;
        $existing = (int) get_post_meta( $attachment_id, $meta_key, true );

        if ( $existing && estate_office_is_attachment_available( $existing ) ) {
            return $existing;
        }

        $created = estate_office_create_watermarked_attachment( $attachment_id, $watermark_id, $hash );
        if ( $created ) {
            update_post_meta( $attachment_id, $meta_key, $created );
        }

        return $created;
    }
}
