<?php

declare(strict_types=1);

/**
 * Front Controller.
 *
 * Unico ponto de entrada da aplicacao acessivel pela web. Tudo passa
 * por aqui e e despachado pelo Kernel (App\Core\App).
 */

// Raiz do projeto (um nivel acima de /public).
define('BASE_PATH', dirname(__DIR__));

// Carrega o Kernel manualmente (o autoloader e registrado dentro dele).
require BASE_PATH . '/app/Core/App.php';

use App\Core\App;

$app = new App(BASE_PATH);
$app->boot();
$app->run();
