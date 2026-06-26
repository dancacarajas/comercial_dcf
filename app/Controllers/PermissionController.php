<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\Permission;

/**
 * Listagem de permissoes (somente leitura nesta etapa).
 */
final class PermissionController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requirePermission('permissions.view');

        $this->view('permissions/index', [
            'title'       => 'Permissões',
            'permissions' => (new Permission())->allOrdered(),
        ]);
    }
}
