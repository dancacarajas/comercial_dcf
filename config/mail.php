<?php

declare(strict_types=1);

/**
 * Configuracao de e-mail.
 *
 * Nesta etapa apenas armazenamos as credenciais. O envio em si sera
 * implementado em etapas futuras (via mail() nativo ou SMTP leve).
 * Na Hostinger, crie a conta de e-mail no hPanel e use o SMTP indicado.
 */

return [
    // Driver previsto: 'smtp' ou 'mail' (funcao nativa do PHP).
    'driver' => env('MAIL_DRIVER', 'smtp'),

    'host'       => env('MAIL_HOST', 'smtp.hostinger.com'),
    'port'       => (int) env('MAIL_PORT', '465'),
    'encryption' => env('MAIL_ENCRYPTION', 'ssl'), // ssl ou tls
    'username'   => env('MAIL_USERNAME', 'no-reply@seudominio.com'),
    'password'   => env('MAIL_PASSWORD', ''),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@seudominio.com'),
        'name'    => env('MAIL_FROM_NAME', 'Dança Carajás Captação'),
    ],
];
