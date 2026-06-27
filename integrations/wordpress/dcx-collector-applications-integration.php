<?php
/**
 * @deprecated Use dcx-crm-collectors-integration.php (mesmo padrão da integração de leads).
 *
 * Integração WordPress — Captadores de Recursos → CRM
 * Copiar para: wp-content/novamira-sandbox/dcx-crm-collectors-integration.php
 *
 * Configurar via wp option dcx_crm_collectors_settings ou:
 * update_option('dcx_crm_collectors_settings', [
 *     'enabled'  => '1',
 *     'endpoint' => 'https://comercial.dancacarajas.com.br/api/collectors/site',
 *     'token'    => 'MESMO_VALOR_DO_COLLECTOR_ENDPOINT_SECRET',
 * ]);
 *
 * Endpoint público CRM: POST /api/collectors/site (header X-DCF-Collector-Token)
 * NÃO enviar para /collector-applications — rota interna com login + CSRF.
 */

declare(strict_types=1);

$target = __DIR__ . '/dcx-crm-collectors-integration.php';
if (is_file($target)) {
    require_once $target;
}
