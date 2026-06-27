<?php
/**
 * Integração WordPress — Captadores de Recursos → CRM
 * Copiar para: wp-content/novamira-sandbox/dcx-collector-applications-integration.php
 *
 * Configurar em wp-config.php ou constante:
 * define('DCX_COLLECTOR_CRM_URL', 'https://comercial.dancacarajas.com.br/api/collectors/site');
 * define('DCX_COLLECTOR_CRM_TOKEN', 'seu-token-secreto');
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', static function (): void {
    register_rest_route('dcx-crm/v1', '/collector-application', [
        'methods'             => 'POST',
        'callback'            => 'dcx_crm_relay_collector_application',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Relay server-side — token nunca exposto ao navegador.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function dcx_crm_relay_collector_application(WP_REST_Request $request): WP_REST_Response
{
    $honeypot = trim((string) $request->get_param('website_url'));
    if ($honeypot !== '') {
        return new WP_REST_Response(['success' => true, 'message' => 'Manifestação recebida com sucesso.'], 201);
    }

    $crmUrl   = defined('DCX_COLLECTOR_CRM_URL') ? DCX_COLLECTOR_CRM_URL : '';
    $crmToken = defined('DCX_COLLECTOR_CRM_TOKEN') ? DCX_COLLECTOR_CRM_TOKEN : '';

    if ($crmUrl === '' || $crmToken === '') {
        return new WP_REST_Response(['success' => false, 'message' => 'Integração não configurada.'], 503);
    }

    $payload = [
        'name'                        => sanitize_text_field((string) $request->get_param('name')),
        'company_or_activity'         => sanitize_text_field((string) $request->get_param('company_or_activity')),
        'document_number'             => sanitize_text_field((string) $request->get_param('document_number')),
        'email'                       => sanitize_email((string) $request->get_param('email')),
        'phone_whatsapp'              => sanitize_text_field((string) $request->get_param('phone_whatsapp')),
        'city_state'                  => sanitize_text_field((string) $request->get_param('city_state')),
        'rouanet_experience'          => sanitize_text_field((string) $request->get_param('rouanet_experience')),
        'segments'                    => sanitize_text_field((string) $request->get_param('segments')),
        'sponsor_network_description' => sanitize_textarea_field((string) $request->get_param('sponsor_network_description')),
        'message'                     => sanitize_textarea_field((string) $request->get_param('message')),
        'consent_contact'             => $request->get_param('consent_contact') ? '1' : '',
        'source_page'                 => 'patrocinio/captadores-de-recursos',
        'source_url'                  => esc_url_raw((string) $request->get_header('referer')),
    ];

    $response = wp_remote_post($crmUrl, [
        'timeout' => 20,
        'headers' => [
            'Content-Type'           => 'application/json',
            'X-DCF-Collector-Token'  => $crmToken,
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Falha ao enviar manifestação. Tente novamente.'], 502);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $message = is_array($body) ? (string) ($body['message'] ?? 'Erro ao processar.') : 'Erro ao processar.';

    return new WP_REST_Response(
        ['success' => $code >= 200 && $code < 300, 'message' => $message],
        $code >= 400 ? $code : 201
    );
}
