<?php
/**
 * Logika kalkulatorów finansowych EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Calculators_Service
 */
class Estate_Office_Calculators_Service {

    /**
     * Stawka VAT dla taksy notarialnej.
     */
    private const VAT_RATE = 0.23;

    /**
     * Stawka podatku PCC dla transakcji sprzedaży/kupna.
     */
    private const PCC_RATE = 0.02;

    /**
     * Opłata za wpis do księgi wieczystej.
     */
    private const LAND_REGISTER_FEE = 200.0;

    /**
     * Średni koszt wypisów aktu notarialnego.
     */
    private const NOTARY_EXTRACTS_FEE = 150.0;

    /**
     * Przedziały taksy notarialnej (zgodnie z maksymalnymi stawkami).
     *
     * @return array<int,array<string,float|null>>
     */
    public function get_notary_thresholds() : array {
        return [
            [ 'min' => 0.0,       'max' => 3000.0,     'base' => 100.0,  'percent' => 0.0 ],
            [ 'min' => 3000.0,    'max' => 10000.0,    'base' => 100.0,  'percent' => 0.03 ],
            [ 'min' => 10000.0,   'max' => 30000.0,    'base' => 310.0,  'percent' => 0.02 ],
            [ 'min' => 30000.0,   'max' => 60000.0,    'base' => 710.0,  'percent' => 0.01 ],
            [ 'min' => 60000.0,   'max' => 1000000.0,  'base' => 1210.0, 'percent' => 0.004 ],
            [ 'min' => 1000000.0, 'max' => 2000000.0,  'base' => 4770.0, 'percent' => 0.002 ],
            [ 'min' => 2000000.0, 'max' => null,       'base' => 6770.0, 'percent' => 0.0025, 'cap' => 10000.0 ],
        ];
    }

    /**
     * Oblicza koszty notarialne.
     *
     * @param float  $price            Cena nieruchomości.
     * @param string $transaction_type Typ transakcji.
     *
     * @return array<string,float>
     */
    public function calculate_notary_fee( float $price, string $transaction_type = 'SPRZEDAŻ' ) : array {
        $price = max( 0.0, $price );

        $thresholds = $this->get_notary_thresholds();
        $base_fee   = 0.0;

        foreach ( $thresholds as $threshold ) {
            $min     = (float) $threshold['min'];
            $max     = isset( $threshold['max'] ) ? (float) $threshold['max'] : null;
            $percent = (float) $threshold['percent'];
            $base    = (float) $threshold['base'];

            if ( $price < $min ) {
                continue;
            }

            if ( null !== $max && $price > $max ) {
                continue;
            }

            $base_fee = $base + max( 0.0, ( $price - $min ) * $percent );

            if ( isset( $threshold['cap'] ) ) {
                $base_fee = min( $base_fee, (float) $threshold['cap'] );
            }

            break;
        }

        if ( $base_fee <= 0 ) {
            $base_fee = 100.0;
        }

        $vat         = $base_fee * self::VAT_RATE;
        $pcc         = in_array( strtoupper( $transaction_type ), [ 'SPRZEDAŻ', 'KUPNO' ], true ) ? $price * self::PCC_RATE : 0.0;
        $land_entry  = self::LAND_REGISTER_FEE;
        $extracts    = self::NOTARY_EXTRACTS_FEE;
        $gross_notary = $base_fee + $vat;
        $total       = $gross_notary + $pcc + $land_entry + $extracts;

        return [
            'base_fee'      => round( $base_fee, 2 ),
            'vat'           => round( $vat, 2 ),
            'gross_notary'  => round( $gross_notary, 2 ),
            'pcc'           => round( $pcc, 2 ),
            'land_register' => round( $land_entry, 2 ),
            'extracts'      => round( $extracts, 2 ),
            'total'         => round( $total, 2 ),
        ];
    }

    /**
     * Oblicza parametry kredytu hipotecznego.
     *
     * @param float $price         Cena nieruchomości.
     * @param float $down_payment  Wkład własny.
     * @param float $interest_rate Oprocentowanie nominalne (%).
     * @param int   $years         Okres kredytowania w latach.
     *
     * @return array<string,float>
     */
    public function calculate_mortgage( float $price, float $down_payment, float $interest_rate, int $years ) : array {
        $price        = max( 0.0, $price );
        $down_payment = max( 0.0, min( $down_payment, $price ) );
        $principal    = max( 0.0, $price - $down_payment );
        $months       = max( 1, $years * 12 );
        $rate         = max( 0.0, $interest_rate ) / 100 / 12;

        if ( $rate > 0 ) {
            $payment = $principal * ( $rate / ( 1 - pow( 1 + $rate, -$months ) ) );
        } else {
            $payment = $principal / $months;
        }

        $total_payment  = $payment * $months;
        $total_interest = $total_payment - $principal;

        return [
            'principal'      => round( $principal, 2 ),
            'monthly_payment'=> round( $payment, 2 ),
            'total_payment'  => round( $total_payment, 2 ),
            'total_interest' => round( max( 0.0, $total_interest ), 2 ),
        ];
    }

    /**
     * Dane pomocnicze przekazywane do warstwy JS.
     *
     * @return array<string,mixed>
     */
    public function get_script_config() : array {
        return [
            'locale'      => get_locale() ?: 'pl_PL',
            'currency'    => 'PLN',
            'vatRate'     => self::VAT_RATE,
            'pccRate'     => self::PCC_RATE,
            'landFee'     => self::LAND_REGISTER_FEE,
            'extractsFee' => self::NOTARY_EXTRACTS_FEE,
            'thresholds'  => array_map(
                static function ( array $row ) : array {
                    $data = [
                        'min'     => (float) $row['min'],
                        'base'    => (float) $row['base'],
                        'percent' => (float) $row['percent'],
                    ];

                    if ( isset( $row['max'] ) && null !== $row['max'] ) {
                        $data['max'] = (float) $row['max'];
                    }

                    if ( isset( $row['cap'] ) ) {
                        $data['cap'] = (float) $row['cap'];
                    }

                    return $data;
                },
                $this->get_notary_thresholds()
            ),
        ];
    }
}
