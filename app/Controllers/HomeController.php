<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use Throwable;

/**
 * Controller da tela inicial publica.
 *
 * Exibe a confirmacao de que a base esta instalada e faz um teste
 * leve de conexao com o banco (sem expor credenciais).
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        $dbConnected = false;

        try {
            Database::connection()->query('SELECT 1');
            $dbConnected = true;
        } catch (Throwable $e) {
            $dbConnected = false;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';

        $this->view('home/index', [
            'title'       => 'Início',
            'phpVersion'  => PHP_VERSION,
            'dbConnected' => $dbConnected,
            'env'         => (string) ($appConfig['env'] ?? 'production'),
        ], 'layouts/admin');
    }
}
