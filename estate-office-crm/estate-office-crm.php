<?php
/**
 * Plugin Name:       EstateOffice CRM
 * Plugin URI:        https://example.com/estateoffice-crm
 * Description:       EstateOffice to kompleksowy CRM dla biur nieruchomości integrujący zarządzanie nieruchomościami, umowami, klientami i agentami.
 * Version:           0.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            EstateOffice Team
 * Author URI:        https://example.com
 * License:           Proprietary
 * License URI:       https://example.com/license
 * Text Domain:       estate-office-crm
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-plugin.php';

\EstateOfficeCRM\Plugin::instance();
