<?php
namespace EstateOffice;

/**
 * Helper utilities for contract handling.
 */
class Contract {
/**
 * Get default contract stages.
 *
 * @return array
 */
public static function get_stages() {
return array(
'umowa_posrednictwa'   => __( 'Umowa Pośrednictwa', 'estate-office' ),
'publikacja_mls'       => __( 'Publikacja w MLS', 'estate-office' ),
'przygotowanie_oferty' => __( 'Przygotowanie oferty', 'estate-office' ),
'publikacja_oferty'    => __( 'Publikacja oferty', 'estate-office' ),
'marketing_prezentacje'=> __( 'Marketing i prezentacje', 'estate-office' ),
'oferta_kupna'         => __( 'Oferta kupna', 'estate-office' ),
'negocjacje'           => __( 'Negocjacje', 'estate-office' ),
'umowa_przedwstepna'   => __( 'Umowa przedwstępna', 'estate-office' ),
'umowa_przyrzeczona'   => __( 'Umowa przyrzeczona', 'estate-office' ),
'przekazanie_lokalu'   => __( 'Przekazanie lokalu', 'estate-office' ),
'umowa_zakonczona'     => __( 'Umowa zakończona', 'estate-office' ),
);
}
}
