<?php

declare(strict_types=1);

/**
 * Configuracao geral da aplicacao.
 *
 * Os valores podem ser definidos por variaveis de ambiente (recomendado)
 * ou editados diretamente neste arquivo (caso a Hostinger nao permita
 * variaveis de ambiente no plano contratado).
 */

return [
    // Nome exibido no painel administrativo.
    'name' => env('APP_NAME', 'Dança Carajás Captação'),

    // Ambiente: 'production' ou 'development'.
    'env' => env('APP_ENV', 'production'),

    // Em producao mantenha SEMPRE como false. Em true, mostra erros na tela.
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),

    // URL base do sistema (sem barra final). Ex.: https://captacao.seudominio.com
    'url' => rtrim((string) env('APP_URL', ''), '/'),

    // Fuso horario padrao (exibicao e regras de negocio — horario de Brasilia).
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),

    // Idioma padrao.
    'locale' => env('APP_LOCALE', 'pt_BR'),

    // Chave usada para assinaturas/hash internos. GERE UMA NOVA na instalacao.
    // Ex.: bin2hex(random_bytes(32))
    'key' => env('APP_KEY', 'TROQUE-ESTA-CHAVE-NA-INSTALACAO'),

    // Timeout por inatividade (em segundos). Padrao: 120 minutos.
    'session_timeout' => (int) env('SESSION_TIMEOUT', '7200'),

    // Configuracoes de sessao segura.
    'session' => [
        'name'            => env('SESSION_NAME', 'DCC_SESSION'),
        'lifetime'        => (int) env('SESSION_LIFETIME', '7200'), // segundos
        'secure'          => filter_var(env('SESSION_SECURE', 'true'), FILTER_VALIDATE_BOOLEAN),
        'httponly'        => true,
        'samesite'        => env('SESSION_SAMESITE', 'Lax'),
        'regenerate_each' => (int) env('SESSION_REGENERATE', '900'), // regenera id a cada X seg
    ],

    // Politica de tentativas de login (protecao simples por sessao/IP).
    'auth' => [
        'max_login_attempts' => (int) env('AUTH_MAX_ATTEMPTS', '5'),
        'lockout_seconds'    => (int) env('AUTH_LOCKOUT_SECONDS', '900'), // 15 minutos
    ],

    // Endpoint público de leads (Etapa 9 — WordPress → CRM)
    'lead_endpoint_enabled'        => filter_var(env('LEAD_ENDPOINT_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
    'lead_endpoint_secret'         => env('LEAD_ENDPOINT_SECRET', ''),
    'lead_rate_limit_minutes'      => (int) env('LEAD_RATE_LIMIT_MINUTES', '10'),
    'lead_rate_limit_max_attempts' => (int) env('LEAD_RATE_LIMIT_MAX_ATTEMPTS', '5'),

    // Endpoint público de captadores (Etapa 18 — WordPress → CRM)
    'collector_endpoint_enabled'        => filter_var(env('COLLECTOR_ENDPOINT_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
    'collector_endpoint_secret'         => env('COLLECTOR_ENDPOINT_SECRET', ''),
    'collector_rate_limit_minutes'      => (int) env('COLLECTOR_RATE_LIMIT_MINUTES', '10'),
    'collector_rate_limit_max_attempts' => (int) env('COLLECTOR_RATE_LIMIT_MAX_ATTEMPTS', '5'),

    // Dados da organização para contratos (Etapa 18B)
    'organization' => [
        'name'                => env('ORG_NAME', 'Dança Carajás Festival'),
        'document'            => env('ORG_DOCUMENT', '40.041.396/0001-30'),
        'address'             => env('ORG_ADDRESS', 'Avenida Brasil, nº 69, Bairro Rio Verde, CEP 68.515-000, Parauapebas/PA'),
        'email'               => env('ORG_EMAIL', ''),
        'phone'               => env('ORG_PHONE', ''),
        'representative_name' => env('ORG_REPRESENTATIVE', ''),
        'legal_entity'        => [
            'name'       => env('ORG_LEGAL_NAME', 'JA PRODUÇÕES ARTÍSTICAS LTDA'),
            'trade_name' => env('ORG_LEGAL_TRADE', 'JARBAS ALVES PRODUÇÕES ARTÍSTICAS'),
            'document'   => env('ORG_LEGAL_DOCUMENT', '40.041.396/0001-30'),
            'role'       => env('ORG_LEGAL_ROLE', 'Responsável jurídica pela contratação'),
        ],
        'branding' => [
            'festival_logo' => 'assets/img/branding/danca-carajas-logo.png',
            'producer_logo' => 'assets/img/branding/ja-producoes-logo.png',
        ],
    ],

    // Padrões contratuais (captador externo)
    'contract' => [
        'default_compensation_percentage' => env('CONTRACT_COMPENSATION_PCT', '10'),
        'default_payment_term'            => env('CONTRACT_PAYMENT_TERM', '30 (trinta) dias após o recebimento dos recursos pela CONTRATANTE'),
        'default_payment_method'          => env('CONTRACT_PAYMENT_METHOD', 'transferência bancária'),
        'default_compensation_notes'      => env('CONTRACT_COMPENSATION_NOTES', 'Conforme termo aditivo ou autorização comercial específica, quando aplicável.'),
        'default_forum'                   => env('CONTRACT_FORUM', 'Parauapebas/PA'),
        'default_duration_months'           => (int) env('CONTRACT_DURATION_MONTHS', '12'),
        'default_exclusivity_type'          => env('CONTRACT_EXCLUSIVITY_TYPE', 'Não exclusiva (salvo termo aditivo específico)'),
        'default_exclusivity_scope'         => env('CONTRACT_EXCLUSIVITY_SCOPE', 'Conforme autorização comercial da CONTRATANTE'),
        'default_exclusivity_period'        => env('CONTRACT_EXCLUSIVITY_PERIOD', 'Conforme vigência contratual'),
        'auto_sign_contratante'             => filter_var(env('CONTRACT_AUTO_SIGN_CONTRATANTE', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],
];
