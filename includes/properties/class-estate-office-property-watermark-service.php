<?php
/**
 * Serwis znakowania zdjęć nieruchomości znakiem wodnym.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Property_Watermark_Service
 */
class Estate_Office_Property_Watermark_Service {

    private const SETTINGS_OPTION_KEY = 'estate_office_settings';
    private const META_WATERMARKED_ATTACHMENT = '_estate_office_watermarked_attachment';
    private const META_WATERMARK_SIGNATURE    = '_estate_office_watermark_signature';
    private const META_SOURCE_ATTACHMENT      = '_estate_office_watermarked_source';

    /**
     * Przetwarza wskazane załączniki i zwraca identyfikatory zdjęć z nałożonym znakiem wodnym.
     *
     * @param array<int,int> $attachment_ids Lista ID załączników.
     *
     * @return array<int,int>
     */
    public function process_gallery( array $attachment_ids ) : array {
        if ( empty( $attachment_ids ) ) {
            return [];
        }

        $attachment_ids = array_values( array_unique( array_map( 'absint', $attachment_ids ) ) );

        $settings = get_option( self::SETTINGS_OPTION_KEY, [] );
        $watermark_id = isset( $settings['watermark_id'] ) ? absint( $settings['watermark_id'] ) : 0;

        if ( $watermark_id <= 0 ) {
            return array_map( [ $this, 'resolve_source_attachment' ], $attachment_ids );
        }

        $watermark_path = get_attached_file( $watermark_id );
        if ( ! $watermark_path || ! file_exists( $watermark_path ) ) {
            Estate_Office_Plugin::log_debug(
                'Nie znaleziono pliku znaku wodnego.',
                [ 'watermark_id' => $watermark_id ]
            );

            return array_map( [ $this, 'resolve_source_attachment' ], $attachment_ids );
        }

        $signature = md5_file( $watermark_path );
        if ( false === $signature ) {
            Estate_Office_Plugin::log_debug( 'Nie udało się wyliczyć sygnatury znaku wodnego.', [ 'watermark_id' => $watermark_id ] );

            return array_map( [ $this, 'resolve_source_attachment' ], $attachment_ids );
        }

        $processed = [];
        foreach ( $attachment_ids as $attachment_id ) {
            $result = $this->ensure_watermarked_attachment( $attachment_id, $watermark_path, $signature );
            if ( $result > 0 ) {
                $processed[] = $result;
            }
        }

        return array_values( array_unique( array_map( 'absint', $processed ) ) );
    }

    /**
     * Zapewnia, że istnieje wariant zdjęcia z aktualnym znakiem wodnym.
     *
     * @param int    $attachment_id ID zdjęcia (oryginalnego lub wariantu).
     * @param string $watermark_path Ścieżka do pliku znaku wodnego.
     * @param string $signature      Sygnatura znaku wodnego.
     *
     * @return int ID załącznika ze znakiem wodnym lub 0 przy błędzie.
     */
    private function ensure_watermarked_attachment( int $attachment_id, string $watermark_path, string $signature ) : int {
        $source_attachment_id = $this->resolve_source_attachment( $attachment_id );
        if ( $source_attachment_id <= 0 ) {
            return 0;
        }

        if ( ! wp_attachment_is_image( $source_attachment_id ) ) {
            return $attachment_id;
        }

        $existing_watermarked_id = absint( get_post_meta( $source_attachment_id, self::META_WATERMARKED_ATTACHMENT, true ) );
        $existing_signature      = (string) get_post_meta( $source_attachment_id, self::META_WATERMARK_SIGNATURE, true );

        if ( $existing_watermarked_id > 0 && $existing_signature === $signature ) {
            if ( get_post( $existing_watermarked_id ) instanceof WP_Post ) {
                return $existing_watermarked_id;
            }
            delete_post_meta( $source_attachment_id, self::META_WATERMARKED_ATTACHMENT );
        }

        if ( $existing_watermarked_id > 0 && $existing_signature !== $signature ) {
            wp_delete_attachment( $existing_watermarked_id, true );
            delete_post_meta( $source_attachment_id, self::META_WATERMARKED_ATTACHMENT );
        }

        $generated_id = $this->generate_watermarked_attachment( $source_attachment_id, $watermark_path, $signature );
        if ( $generated_id <= 0 ) {
            return $attachment_id;
        }

        update_post_meta( $source_attachment_id, self::META_WATERMARKED_ATTACHMENT, $generated_id );
        update_post_meta( $source_attachment_id, self::META_WATERMARK_SIGNATURE, $signature );
        update_post_meta( $generated_id, self::META_SOURCE_ATTACHMENT, $source_attachment_id );
        update_post_meta( $generated_id, self::META_WATERMARK_SIGNATURE, $signature );

        return $generated_id;
    }

    /**
     * Zwraca ID oryginalnego załącznika dla wariantu.
     *
     * @param int $attachment_id ID załącznika.
     *
     * @return int
     */
    private function resolve_source_attachment( int $attachment_id ) : int {
        if ( $attachment_id <= 0 ) {
            return 0;
        }

        $source_id = absint( get_post_meta( $attachment_id, self::META_SOURCE_ATTACHMENT, true ) );
        if ( $source_id > 0 && get_post( $source_id ) instanceof WP_Post ) {
            return $source_id;
        }

        return $attachment_id;
    }

    /**
     * Tworzy nowy załącznik z nałożonym znakiem wodnym.
     *
     * @param int    $attachment_id  ID oryginalnego załącznika.
     * @param string $watermark_path Ścieżka do znaku wodnego.
     * @param string $signature      Sygnatura znaku wodnego.
     *
     * @return int
     */
    private function generate_watermarked_attachment( int $attachment_id, string $watermark_path, string $signature ) : int {
        $source_path = get_attached_file( $attachment_id );
        if ( ! $source_path || ! file_exists( $source_path ) ) {
            Estate_Office_Plugin::log_debug(
                'Nie można odczytać źródłowego zdjęcia do znakowania.',
                [ 'attachment_id' => $attachment_id ]
            );

            return 0;
        }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            Estate_Office_Plugin::log_debug( 'Błąd katalogu upload podczas znakowania.', [ 'error' => $uploads['error'] ] );

            return 0;
        }

        $subdir = trim( $uploads['subdir'], '/' );
        if ( '' !== $subdir ) {
            $subdir .= '/';
        }
        $subdir          .= 'estate-office-watermarked';
        $destination_dir  = trailingslashit( $uploads['basedir'] ) . $subdir;
        $destination_url  = trailingslashit( $uploads['baseurl'] ) . str_replace( DIRECTORY_SEPARATOR, '/', $subdir );

        if ( ! wp_mkdir_p( $destination_dir ) ) {
            Estate_Office_Plugin::log_debug( 'Nie udało się utworzyć katalogu na znakowane zdjęcia.', [ 'path' => $destination_dir ] );

            return 0;
        }

        $source_info = pathinfo( $source_path );
        $extension   = isset( $source_info['extension'] ) ? strtolower( $source_info['extension'] ) : 'jpg';
        $base_name   = isset( $source_info['filename'] ) ? $source_info['filename'] : 'estate-office-image';

        $file_name = wp_unique_filename(
            $destination_dir,
            $base_name . '-watermarked-' . substr( $signature, 0, 8 ) . '.' . $extension
        );

        $destination_path = trailingslashit( $destination_dir ) . $file_name;

        if ( ! $this->apply_watermark( $source_path, $watermark_path, $destination_path ) ) {
            return 0;
        }

        $file_type = wp_check_filetype( $destination_path );
        $attachment_post = [
            'post_mime_type' => $file_type['type'] ?? 'image/jpeg',
            'post_title'     => get_the_title( $attachment_id ) . ' (watermark)',
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => (int) get_post_field( 'post_parent', $attachment_id ),
            'guid'           => trailingslashit( $destination_url ) . $file_name,
        ];

        $new_attachment_id = wp_insert_attachment( $attachment_post, $destination_path );
        if ( ! $new_attachment_id || is_wp_error( $new_attachment_id ) ) {
            Estate_Office_Plugin::log_debug(
                'Nie udało się utworzyć załącznika ze znakiem wodnym.',
                [ 'attachment_id' => $attachment_id ]
            );

            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $new_attachment_id, $destination_path );
        wp_update_attachment_metadata( $new_attachment_id, $metadata );

        $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( ! empty( $alt_text ) ) {
            update_post_meta( $new_attachment_id, '_wp_attachment_image_alt', $alt_text );
        }

        return (int) $new_attachment_id;
    }

    /**
     * Nakłada znak wodny na plik docelowy.
     *
     * @param string $source_path      Ścieżka źródła.
     * @param string $watermark_path   Ścieżka znaku wodnego.
     * @param string $destination_path Ścieżka docelowa.
     *
     * @return bool
     */
    private function apply_watermark( string $source_path, string $watermark_path, string $destination_path ) : bool {
        if ( class_exists( 'Imagick' ) ) {
            return $this->apply_with_imagick( $source_path, $watermark_path, $destination_path );
        }

        if ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagecopy' ) ) {
            return $this->apply_with_gd( $source_path, $watermark_path, $destination_path );
        }

        Estate_Office_Plugin::log_debug( 'Brak wsparcia GD/Imagick do nakładania znaku wodnego.' );

        return false;
    }

    /**
     * Nakładanie znaku wodnego z użyciem Imagick.
     */
    private function apply_with_imagick( string $source_path, string $watermark_path, string $destination_path ) : bool {
        try {
            $image     = new Imagick( $source_path );
            $watermark = new Imagick( $watermark_path );

            $image->setImageColorspace( Imagick::COLORSPACE_RGB );
            $image->setImageAlphaChannel( Imagick::ALPHACHANNEL_SET );

            $image_width  = $image->getImageWidth();
            $image_height = $image->getImageHeight();
            $wm_width     = $watermark->getImageWidth();
            $wm_height    = $watermark->getImageHeight();

            $margin = (int) max( 10, round( min( $image_width, $image_height ) * 0.02 ) );
            $x      = max( 0, $image_width - $wm_width - $margin );
            $y      = max( 0, $image_height - $wm_height - $margin );

            $image->compositeImage( $watermark, Imagick::COMPOSITE_OVER, $x, $y );
            $result = $image->writeImage( $destination_path );

            $watermark->destroy();
            $image->destroy();

            return (bool) $result;
        } catch ( Exception $exception ) {
            Estate_Office_Plugin::log_debug(
                'Błąd Imagick podczas nakładania znaku wodnego.',
                [ 'error' => $exception->getMessage() ]
            );

            return false;
        }
    }

    /**
     * Nakładanie znaku wodnego przy użyciu biblioteki GD.
     */
    private function apply_with_gd( string $source_path, string $watermark_path, string $destination_path ) : bool {
        $source_image    = $this->create_gd_image( $source_path );
        $watermark_image = $this->create_gd_image( $watermark_path );

        if ( ! $source_image || ! $watermark_image ) {
            if ( $source_image ) {
                imagedestroy( $source_image );
            }
            if ( $watermark_image ) {
                imagedestroy( $watermark_image );
            }

            Estate_Office_Plugin::log_debug( 'Nie udało się utworzyć zasobów GD dla znakowania.' );

            return false;
        }

        $source_width  = imagesx( $source_image );
        $source_height = imagesy( $source_image );
        $wm_width      = imagesx( $watermark_image );
        $wm_height     = imagesy( $watermark_image );

        $margin = (int) max( 10, round( min( $source_width, $source_height ) * 0.02 ) );
        $x      = max( 0, $source_width - $wm_width - $margin );
        $y      = max( 0, $source_height - $wm_height - $margin );

        imagealphablending( $source_image, true );
        imagesavealpha( $source_image, true );
        imagealphablending( $watermark_image, true );
        imagesavealpha( $watermark_image, true );

        if ( ! imagecopy( $source_image, $watermark_image, $x, $y, 0, 0, $wm_width, $wm_height ) ) {
            imagedestroy( $source_image );
            imagedestroy( $watermark_image );

            Estate_Office_Plugin::log_debug( 'Błąd GD podczas nakładania znaku wodnego.' );

            return false;
        }

        $saved = $this->save_gd_image( $source_image, $destination_path );

        imagedestroy( $source_image );
        imagedestroy( $watermark_image );

        return $saved;
    }

    /**
     * Tworzy obraz GD na podstawie pliku.
     *
     * @param string $file Ścieżka do pliku.
     *
     * @return GdImage|resource|false
     */
    private function create_gd_image( string $file ) {
        $info = getimagesize( $file );
        if ( false === $info ) {
            return false;
        }

        switch ( $info[2] ) {
            case IMAGETYPE_PNG:
                return imagecreatefrompng( $file );
            case IMAGETYPE_GIF:
                return imagecreatefromgif( $file );
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg( $file );
            case IMAGETYPE_WEBP:
                if ( function_exists( 'imagecreatefromwebp' ) ) {
                    return imagecreatefromwebp( $file );
                }
                break;
        }

        return false;
    }

    /**
     * Zapisuje obraz GD do pliku na podstawie rozszerzenia.
     */
    private function save_gd_image( $image, string $destination_path ) : bool {
        $extension = strtolower( pathinfo( $destination_path, PATHINFO_EXTENSION ) );

        switch ( $extension ) {
            case 'png':
                imagesavealpha( $image, true );
                return imagepng( $image, $destination_path );
            case 'gif':
                return imagegif( $image, $destination_path );
            case 'webp':
                if ( function_exists( 'imagewebp' ) ) {
                    return imagewebp( $image, $destination_path );
                }
                return false;
            default:
                return imagejpeg( $image, $destination_path, 90 );
        }
    }
}
