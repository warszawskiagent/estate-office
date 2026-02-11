<?php
if (! defined('ABSPATH')) {
    exit;
}

$tabs = [
    'dashboard' => 'Pulpit',
    'properties' => 'Nieruchomości',
    'searches' => 'Poszukiwania',
    'agreements' => 'Umowy',
    'clients' => 'Klienci',
];
?>
<div class="estateoffice-crm">
    <nav class="estateoffice-crm__menu">
        <?php foreach ($tabs as $tabKey => $tabLabel) : ?>
            <a class="estateoffice-crm__menu-link <?php echo $tabKey === $view ? 'is-active' : ''; ?>"
               href="<?php echo esc_url(add_query_arg(['eo_view' => $tabKey])); ?>">
                <?php echo esc_html($tabLabel); ?>
            </a>
        <?php endforeach; ?>
        <a class="estateoffice-crm__add-agreement" href="#">+ Dodaj nową Umowę</a>
    </nav>

    <section class="estateoffice-crm__content">
        <?php include $template; ?>
    </section>
</div>
