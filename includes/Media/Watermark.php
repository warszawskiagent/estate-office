<?php
namespace EstateOffice\Media;

use EstateOffice\Meta\Keys;
use EstateOffice\Settings\Manager as SettingsManager;
use WP_Post;

/**
 * Applies watermarks to property media based on plugin settings.
 */
class Watermark {
    private SettingsManager $settings;

    public function __construct( SettingsManager $settings ) {
        $this->settings = $settings;
    }

    /**
     * Applies the configured watermark to all provided gallery attachments.
     *
     * @param int   $property_id    The property identifier.
     * @param int[] $attachment_ids Attachment identifiers.
     */
    public function apply_to_gallery( int $property_id, array $attachment_ids ): void {
        $attachment_ids = array_filter(
            array_map( 'intval', $attachment_ids ),
            static function ( int $id ): bool {
                return $id > 0;
            }
        );

        if ( empty( $attachment_ids ) ) {
            return;
        }

        $settings      = $this->settings->get_settings();
        $watermark_id  = isset( $settings['watermark'] ) ? (int) $settings['watermark'] : 0;
        $watermark_path = $watermark_id ? get_attached_file( $watermark_id ) : '';

        if ( ! $watermark_path || ! file_exists( $watermark_path ) ) {
            return;
        }

        foreach ( $attachment_ids as $attachment_id ) {
            $this->apply_to_attachment( $attachment_id, $watermark_path );
        }
    }

    /**
     * Applies watermark when property post is saved.
     */
    public function apply_on_save( int $post_id, WP_Post $post, bool $update ): void {
        if ( 'estate_property' !== $post->post_type ) {
            return;
        }

        $gallery = get_post_meta( $post_id, Keys::PROPERTY_GALLERY, true );
        if ( ! is_array( $gallery ) ) {
            return;
        }

        $this->apply_to_gallery( $post_id, $gallery );
    }

    /**
     * Applies the watermark to a single attachment and its generated sizes.
     */
    private function apply_to_attachment( int $attachment_id, string $watermark_path ): void {
        $mime = get_post_mime_type( $attachment_id );
        if ( ! $mime || strpos( $mime, 'image/' ) !== 0 ) {
            return;
        }

        if ( get_post_meta( $attachment_id, '_estate_office_watermark', true ) ) {
            return;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }

        $paths = [ $file ];

        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && ! empty( $metadata['file'] ) ) {
            $uploads   = wp_upload_dir();
            $base_dir  = trailingslashit( $uploads['basedir'] );
            $directory = trailingslashit( dirname( $metadata['file'] ) );

            foreach ( $metadata['sizes'] as $size ) {
                if ( empty( $size['file'] ) ) {
                    continue;
                }

                $size_path = $base_dir . $directory . $size['file'];
                if ( file_exists( $size_path ) ) {
                    $paths[] = $size_path;
                }
            }
        }

        foreach ( $paths as $path ) {
            $this->overlay_watermark( $path, $watermark_path );
        }

        update_post_meta( $attachment_id, '_estate_office_watermark', time() );
    }

    /**
     * Overlays the watermark on top of the target image.
     */
    private function overlay_watermark( string $target_path, string $watermark_path ): void {
        $target_info    = @getimagesize( $target_path );
        $watermark_info = @getimagesize( $watermark_path );

        if ( false === $target_info || false === $watermark_info ) {
            return;
        }

        $target = $this->create_image_resource( $target_path, $target_info['mime'] ?? '' );
        $stamp  = $this->create_image_resource( $watermark_path, $watermark_info['mime'] ?? '' );

        if ( ! $target || ! $stamp ) {
            return;
        }

        $target_width  = (int) $target_info[0];
        $target_height = (int) $target_info[1];
        $stamp_width   = (int) $watermark_info[0];
        $stamp_height  = (int) $watermark_info[1];

        if ( $stamp_width > $target_width || $stamp_height > $target_height ) {
            $scale = min(
                ( $target_width - 20 ) / max( $stamp_width, 1 ),
                ( $target_height - 20 ) / max( $stamp_height, 1 ),
                1
            );

            if ( $scale < 1 && function_exists( 'imagescale' ) ) {
                $new_width  = max( 1, (int) floor( $stamp_width * $scale ) );
                $new_height = max( 1, (int) floor( $stamp_height * $scale ) );
                $resized    = imagescale( $stamp, $new_width, $new_height, IMG_BILINEAR_FIXED );

                if ( $resized ) {
                    imagedestroy( $stamp );
                    $stamp        = $resized;
                    $stamp_width  = $new_width;
                    $stamp_height = $new_height;
                }
            }
        }

        imagealphablending( $target, true );
        imagesavealpha( $target, true );
        imagealphablending( $stamp, true );
        imagesavealpha( $stamp, true );

        $margin = 24;
        $x      = max( $target_width - $stamp_width - $margin, 0 );
        $y      = max( $target_height - $stamp_height - $margin, 0 );

        imagecopy( $target, $stamp, $x, $y, 0, 0, $stamp_width, $stamp_height );

        $this->save_image_resource( $target, $target_path, $target_info['mime'] ?? '' );

        imagedestroy( $target );
        imagedestroy( $stamp );
    }

    private function create_image_resource( string $path, string $mime ) {
        switch ( $mime ) {
            case 'image/jpeg':
                return imagecreatefromjpeg( $path );
            case 'image/png':
                return imagecreatefrompng( $path );
            case 'image/gif':
                return imagecreatefromgif( $path );
            default:
                return null;
        }
    }

    private function save_image_resource( $resource, string $path, string $mime ): void {
        switch ( $mime ) {
            case 'image/jpeg':
                imagejpeg( $resource, $path, 90 );
                break;
            case 'image/png':
                imagepng( $resource, $path );
                break;
            case 'image/gif':
                imagegif( $resource, $path );
                break;
        }
    }
}
