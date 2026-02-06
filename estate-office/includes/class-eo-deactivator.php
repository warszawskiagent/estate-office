<?php

if (!defined('ABSPATH')) {
    exit;
}

class EstateOffice_Deactivator
{
    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}
