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
