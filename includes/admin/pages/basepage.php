<?php

declare(strict_types=1);

namespace EstateOffice\Admin\Pages;

defined('ABSPATH') || exit;

abstract class BasePage
{
    final public static function render(): void
    {
        $self = new static();
        $self->maybeRender();
    }

    final protected function maybeRender(): void
    {
        if (!$this->canView()) {
            wp_die(esc_html__('Nie masz uprawnień do przeglądania tej strony.', 'estate-office'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($this->getTitle()) . '</h1>';
        $this->renderContent();
        echo '</div>';
    }

    abstract protected function canView(): bool;

    abstract protected function getTitle(): string;

    abstract protected function renderContent(): void;
}
