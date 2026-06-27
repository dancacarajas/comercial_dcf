<?php
/**
 * Plugin Name: DCX CRM Collectors Integration
 * Description: Proxy server-side WordPress → CRM captadores (Etapa 18). Token nunca exposto ao navegador.
 * Copiar para: wp-content/novamira-sandbox/dcx-crm-collectors-integration.php
 * Copiar JS para: wp-content/novamira-sandbox/dcx-crm-collectors.js
 *
 * Configurar no WordPress (mesmo token do COLLECTOR_ENDPOINT_SECRET do CRM):
 * update_option('dcx_crm_collectors_settings', [
 *     'enabled'  => '1',
 *     'endpoint' => 'https://comercial.dancacarajas.com.br/api/collectors/site',
 *     'token'    => 'MESMO_VALOR_DO_COLLECTOR_ENDPOINT_SECRET',
 * ]);
 * Version: 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const DCX_CRM_COLLECTORS_VERSION = '1.0.0';
const DCX_CRM_COLLECTORS_REST_NS = 'dcx-crm/v1';

/** @return array{enabled:string,endpoint:string,token:string} */
function dcx_crm_collectors_settings(): array
{
    $defaults = [
        'enabled'  => '1',
        'endpoint' => 'https://comercial.dancacarajas.com.br/api/collectors/site',
        'token'    => '',
    ];
    $saved = get_option('dcx_crm_collectors_settings', []);

    return wp_parse_args(is_array($saved) ? $saved : [], $defaults);
}

function dcx_crm_collectors_is_captadores_page(): bool
{
    $post = get_queried_object();
    if ($post instanceof WP_Post && $post->post_type === 'page') {
        if ($post->post_name === 'captadores-de-recursos') {
            return true;
        }

        $parent = $post->post_parent ? get_post((int) $post->post_parent) : null;
        if ($parent instanceof WP_Post && $parent->post_name === 'patrocinio') {
            return $post->post_name === 'captadores-de-recursos';
        }
    }

    $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');

    return $path === 'patrocinio/captadores-de-recursos';
}

function dcx_crm_collectors_is_consent_yes(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $v = strtolower(trim((string) $value));

    return in_array($v, ['1', 'true', 'sim', 'yes', 'on', 'autorizado'], true);
}

/** @return array<string, mixed> */
function dcx_crm_collectors_sanitize_payload(array $body): array
{
    $allowed = [
        'name', 'nome', 'company_or_activity', 'empresa', 'document_number', 'documento', 'cpf_cnpj',
        'email', 'phone_whatsapp', 'whatsapp', 'telefone', 'city_state', 'cidade_uf', 'cidade',
        'rouanet_experience', 'experiencia_rouanet', 'segments', 'segmentos',
        'sponsor_network_description', 'carteira', 'carteira_patrocinadores', 'message', 'mensagem',
        'consent_contact', 'autorizacao_contato', 'consent', 'consentimento', 'aceite', 'lgpd',
        'source_page', 'source_url', 'website', 'website_url',
    ];

    $payload = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $body)) {
            continue;
        }

        $value = $body[$key];
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        $payload[$key] = sanitize_text_field((string) $value);
    }

    if (!empty($body['message'])) {
        $payload['message'] = sanitize_textarea_field((string) $body['message']);
    }
    if (!empty($body['mensagem'])) {
        $payload['mensagem'] = sanitize_textarea_field((string) $body['mensagem']);
    }
    if (!empty($body['sponsor_network_description'])) {
        $payload['sponsor_network_description'] = sanitize_textarea_field((string) $body['sponsor_network_description']);
    }
    if (!empty($body['carteira'])) {
        $payload['carteira'] = sanitize_textarea_field((string) $body['carteira']);
    }
    if (!empty($payload['email'])) {
        $payload['email'] = sanitize_email((string) $payload['email']);
    }

    if (empty($payload['name']) && !empty($payload['nome'])) {
        $payload['name'] = (string) $payload['nome'];
    }
    if (empty($payload['company_or_activity']) && !empty($payload['empresa'])) {
        $payload['company_or_activity'] = (string) $payload['empresa'];
    }
    if (empty($payload['document_number']) && !empty($payload['documento'])) {
        $payload['document_number'] = (string) $payload['documento'];
    }
    if (empty($payload['phone_whatsapp']) && !empty($payload['whatsapp'])) {
        $payload['phone_whatsapp'] = (string) $payload['whatsapp'];
    }
    if (empty($payload['rouanet_experience']) && !empty($payload['experiencia_rouanet'])) {
        $payload['rouanet_experience'] = (string) $payload['experiencia_rouanet'];
    }
    if (empty($payload['segments']) && !empty($payload['segmentos'])) {
        $payload['segments'] = (string) $payload['segmentos'];
    }
    if (empty($payload['sponsor_network_description']) && !empty($payload['carteira'])) {
        $payload['sponsor_network_description'] = (string) $payload['carteira'];
    }
    if (empty($payload['message']) && !empty($payload['mensagem'])) {
        $payload['message'] = (string) $payload['mensagem'];
    }

    $consentKeys = ['consent_contact', 'autorizacao_contato', 'consent', 'consentimento', 'aceite', 'lgpd'];
    foreach ($consentKeys as $key) {
        if (!empty($payload[$key]) && dcx_crm_collectors_is_consent_yes($payload[$key])) {
            $payload['consent_contact'] = '1';
            break;
        }
    }

    if (empty($payload['source_page'])) {
        $payload['source_page'] = 'patrocinio/captadores-de-recursos';
    }

    unset($payload['collector_token']);

    return $payload;
}

/**
 * @return array{success:bool,message:string,status:int}
 */
function dcx_crm_collectors_send_to_crm(array $payload): array
{
    $settings = dcx_crm_collectors_settings();
    if (($settings['enabled'] ?? '0') !== '1' || empty($settings['endpoint']) || empty($settings['token'])) {
        error_log('[DCX CRM Collectors] Integração indisponível ou token ausente.');

        return [
            'success' => false,
            'message' => 'Não foi possível processar sua solicitação agora.',
            'status'  => 503,
        ];
    }

    if (!empty($payload['website_url']) || !empty($payload['website'])) {
        return [
            'success' => true,
            'message' => 'Manifestação recebida com sucesso.',
            'status'  => 201,
        ];
    }

    $response = wp_remote_post((string) $settings['endpoint'], [
        'timeout' => 20,
        'headers' => [
            'Content-Type'           => 'application/json; charset=utf-8',
            'Accept'                 => 'application/json',
            'X-DCF-Collector-Token'  => (string) $settings['token'],
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        error_log('[DCX CRM Collectors] wp_error: ' . $response->get_error_message());

        return [
            'success' => false,
            'message' => 'Não foi possível enviar sua manifestação agora. Tente novamente em alguns instantes.',
            'status'  => 503,
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $raw  = (string) wp_remote_retrieve_body($response);
    $json = json_decode($raw, true);

    if ($code === 201 && is_array($json) && ($json['success'] ?? false) === true) {
        return [
            'success' => true,
            'message' => (string) ($json['message'] ?? 'Manifestação recebida com sucesso.'),
            'status'  => 201,
        ];
    }

    if ($code === 422 && is_array($json)) {
        return [
            'success' => false,
            'message' => 'Confira os dados informados e tente novamente.',
            'status'  => 422,
        ];
    }

    if ($code === 429) {
        return [
            'success' => false,
            'message' => 'Muitas tentativas. Tente novamente em alguns instantes.',
            'status'  => 429,
        ];
    }

    error_log('[DCX CRM Collectors] CRM rejeitou manifestação HTTP ' . $code . ' — ' . $raw);

    return [
        'success' => false,
        'message' => 'Não foi possível enviar sua manifestação agora. Tente novamente em alguns instantes.',
        'status'  => $code > 0 ? $code : 502,
    ];
}

function dcx_crm_collectors_register_rest(): void
{
    register_rest_route(DCX_CRM_COLLECTORS_REST_NS, '/collector-application', [
        'methods'             => 'POST',
        'callback'            => 'dcx_crm_collectors_rest_proxy',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'dcx_crm_collectors_register_rest');

/** @return WP_REST_Response */
function dcx_crm_collectors_rest_proxy(WP_REST_Request $request): WP_REST_Response
{
    $body = $request->get_json_params();
    if (!is_array($body) || $body === []) {
        $body = $request->get_body_params();
    }
    if (!is_array($body)) {
        $body = [];
    }

    if (empty($body['source_url'])) {
        $body['source_url'] = esc_url_raw((string) $request->get_header('referer'));
    }

    $payload = dcx_crm_collectors_sanitize_payload($body);
    $result  = dcx_crm_collectors_send_to_crm($payload);

    return new WP_REST_Response([
        'success' => $result['success'],
        'message' => $result['message'],
    ], $result['status']);
}

function dcx_crm_collectors_js_version(): string
{
    $path = WP_CONTENT_DIR . '/novamira-sandbox/dcx-crm-collectors.js';

    return file_exists($path) ? (string) filemtime($path) : DCX_CRM_COLLECTORS_VERSION;
}

function dcx_crm_collectors_enqueue(): void
{
    if (!dcx_crm_collectors_is_captadores_page()) {
        return;
    }

    $settings = dcx_crm_collectors_settings();
    if (($settings['enabled'] ?? '0') !== '1') {
        return;
    }

    wp_enqueue_script(
        'dcx-crm-collectors',
        content_url('novamira-sandbox/dcx-crm-collectors.js'),
        [],
        dcx_crm_collectors_js_version(),
        true
    );

    wp_localize_script('dcx-crm-collectors', 'DCX_CRM_COLLECTORS', [
        'proxyUrl'   => esc_url_raw(rest_url(DCX_CRM_COLLECTORS_REST_NS . '/collector-application')),
        'sourcePage' => 'patrocinio/captadores-de-recursos',
        'sourceUrl'  => esc_url_raw((string) get_permalink()),
    ]);
}
add_action('wp_enqueue_scripts', 'dcx_crm_collectors_enqueue', 21);

function dcx_crm_collectors_script_tag(string $tag, string $handle, string $src): string
{
    if ($handle !== 'dcx-crm-collectors') {
        return $tag;
    }

    if (str_contains($tag, 'data-no-optimize')) {
        return $tag;
    }

    return str_replace('<script ', '<script data-no-optimize="1" data-cfasync="false" ', $tag);
}
add_filter('script_loader_tag', 'dcx_crm_collectors_script_tag', 10, 3);
