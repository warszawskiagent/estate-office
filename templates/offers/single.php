<?php
/**
 * Default single offer layout.
 *
 * @var int                               $property_id
 * @var string                            $transaction_type
 * @var string                            $transaction_label
 * @var string                            $title
 * @var array<int,string>                 $tag_labels
 * @var string                            $address_text
 * @var array<int,array<string,string>>   $highlights
 * @var array<int,array<string,string>>   $gallery
 * @var array<int,array<string,string>>   $floor_plans
 * @var array<string,string>              $media_links
 * @var string|null                       $description
 * @var array<int,array<string,mixed>>    $info_sections
 * @var array<int,string>                 $equipment_labels
 * @var array<string,mixed>               $contract
 * @var array<string,mixed>|null          $agent
 * @var array<string,mixed>               $map
 * @var string                            $brand_badge_html
 * @var string                            $notary_markup
 * @var string                            $mortgage_markup
 * @var string|null                       $notary_link
 * @var string|null                       $mortgage_link
 */
?>
<article
    class="estate-office-offer-page"
    data-offer-id="<?php echo esc_attr( $property_id ); ?>"
    data-transaction="<?php echo esc_attr( $transaction_type ); ?>"
>
    <?php
    estate_office_output_template(
        'offers/parts/hero.php',
        [
            'brand_badge_html'  => $brand_badge_html,
            'transaction_label' => $transaction_label,
            'title'             => $title,
            'tag_labels'        => $tag_labels,
            'address_text'      => $address_text,
            'highlights'        => $highlights,
        ]
    );

    estate_office_output_template( 'offers/parts/gallery.php', [ 'gallery' => $gallery ] );
    estate_office_output_template( 'offers/parts/floorplans.php', [ 'floor_plans' => $floor_plans ] );
    estate_office_output_template( 'offers/parts/media.php', [ 'media_links' => $media_links ] );
    estate_office_output_template( 'offers/parts/description.php', [ 'description' => $description ] );
    estate_office_output_template( 'offers/parts/info-sections.php', [ 'sections' => $info_sections ] );
    estate_office_output_template( 'offers/parts/equipment.php', [ 'equipment_labels' => $equipment_labels ] );
    estate_office_output_template( 'offers/parts/contract.php', [ 'contract' => $contract ] );
    estate_office_output_template( 'offers/parts/agent.php', [ 'agent' => $agent ] );
    estate_office_output_template( 'offers/parts/map.php', [ 'map' => $map ] );
    estate_office_output_template(
        'offers/parts/calculators.php',
        [
            'notary_markup'   => $notary_markup,
            'mortgage_markup' => $mortgage_markup,
            'notary_link'     => $notary_link,
            'mortgage_link'   => $mortgage_link,
        ]
    );
    ?>
</article>
