<?php
/**
 * File-based adapter writing JSON feeds per portal.
 *
 * @package EstateOffice\Portals
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Portal_File_Adapter extends EstateOffice_Portal_Adapter {

    /**
     * Persist payload as JSON document in uploads directory.
     */
    public function send( array $payload ): EstateOffice_Portal_Result {
        $payload = $this->prepare_payload( $payload );

        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return EstateOffice_Portal_Result::failure( __( 'Nie udało się uzyskać katalogu uploadów WordPress.', 'estate-office' ) );
        }

        $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'estate-office/portals/' . $this->get_slug() . '/';
        if ( ! wp_mkdir_p( $base_dir ) ) {
            return EstateOffice_Portal_Result::failure( __( 'Nie udało się utworzyć katalogu eksportu portalu.', 'estate-office' ) );
        }

        $property_id = absint( $payload['id'] ?? 0 );
        if ( ! $property_id ) {
            return EstateOffice_Portal_Result::failure( __( 'Brak identyfikatora nieruchomości w ładunku eksportu.', 'estate-office' ) );
        }

        $file_name = 'property-' . $property_id . '.json';
        $file_path = $base_dir . $file_name;

        $encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === file_put_contents( $file_path, $encoded ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
            return EstateOffice_Portal_Result::failure( __( 'Zapis pliku eksportu nie powiódł się.', 'estate-office' ) );
        }

        $file_url = trailingslashit( $upload_dir['baseurl'] ) . 'estate-office/portals/' . $this->get_slug() . '/' . $file_name;

        return EstateOffice_Portal_Result::success(
            __( 'Eksport zapisany w katalogu portalu.', 'estate-office' ),
            [
                'file_path' => $file_path,
                'file_url'  => $file_url,
            ]
        );
    }
}
