<?php
/**
 * Plugin Name: DCX Sponsorship Simulation Proxy
 * Description: Proxy server-side WordPress → CRM para SPONSORSHIP_SIMULATION (Etapa 22.1). Token nunca no browser.
 * Version: 1.0.0
 * Author: Dança Carajás
 *
 * Secret (obrigatório em wp-config.php):
 *   define('DCX_CRM_LEAD_ENDPOINT_SECRET', '...');
 *
 * Options não secretas (opcional):
 *   update_option('dcx_ssp_settings', [
 *     'enabled' => '1',
 *     'trusted_proxies' => ['127.0.0.1', '::1'],
 *     'timeout_seconds' => 12,
 *   ]);
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DCX_SSP_VERSION', '1.0.0');
define('DCX_SSP_PLUGIN_FILE', __FILE__);
define('DCX_SSP_PLUGIN_DIR', __DIR__);

require_once __DIR__ . '/includes/class-dcx-ssp-constants.php';
require_once __DIR__ . '/includes/class-dcx-ssp-secrets.php';
require_once __DIR__ . '/includes/class-dcx-ssp-settings.php';
require_once __DIR__ . '/includes/class-dcx-ssp-client-ip.php';
require_once __DIR__ . '/includes/class-dcx-ssp-envelope-a.php';
require_once __DIR__ . '/includes/class-dcx-ssp-validator.php';
require_once __DIR__ . '/includes/class-dcx-ssp-envelope-b.php';
require_once __DIR__ . '/includes/class-dcx-ssp-crm-transport.php';
require_once __DIR__ . '/includes/class-dcx-ssp-envelope-c.php';
require_once __DIR__ . '/includes/class-dcx-ssp-logger.php';
require_once __DIR__ . '/includes/class-dcx-ssp-pipeline.php';
require_once __DIR__ . '/includes/class-dcx-ssp-rest-controller.php';

add_action('rest_api_init', static function (): void {
    Dcx_Ssp_Rest_Controller::register();
});
