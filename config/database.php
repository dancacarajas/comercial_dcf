<?php

declare(strict_types=1);

/**
 * Configuracao de conexao com o banco de dados (MySQL/MariaDB via PDO).
 *
 * Na Hostinger, crie o banco e o usuario pelo hPanel > Bancos de Dados MySQL
 * e preencha os valores abaixo (ou via variaveis de ambiente em .env).
 */

return [
    'driver'   => env('DB_DRIVER', 'mysql'),
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => (int) env('DB_PORT', '3306'),

    // Na Hostinger o nome do banco e do usuario costumam ter um prefixo,
    // por exemplo: u123456789_captacao
    'database' => env('DB_DATABASE', 'u000000000_captacao'),
    'username' => env('DB_USERNAME', 'u000000000_user'),
    'password' => env('DB_PASSWORD', ''),

    'charset'   => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),

    // Opcoes de PDO. Prepared statements reais (emulate = false) e excecoes.
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];
