<?php

declare(strict_types=1);

/**
 * Entrada alternativa do instalador (Hostinger compartilhada).
 * Redireciona para o fluxo /install do front controller.
 */
$_SERVER['REQUEST_URI'] = '/install';

require __DIR__ . '/index.php';
