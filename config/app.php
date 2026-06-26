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

    // Fuso horario padrao.
    'timezone' => env('APP_TIMEZONE', 'America/Belem'),

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
];
