<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\InstallerService;
use Throwable;

/**
 * Instalador web temporário (Etapa 9B).
 */
final class InstallController extends Controller
{
    private InstallerService $installer;

    public function __construct()
    {
        $this->installer = new InstallerService();
    }

    public function index(): void
    {
        $this->guardInstallable();
        $this->view('install/requirements', [
            'title'    => 'Instalação — Requisitos',
            'step'     => 1,
            'checks'   => $this->installer->checkRequirements(),
            'canNext'  => $this->installer->requirementsPassed(),
        ], 'layouts/install');
    }

    public function database(): void
    {
        $this->guardInstallable();
        $data = $this->sessionData()['db'] ?? $this->defaultDb();

        $this->view('install/database', [
            'title' => 'Instalação — Banco de dados',
            'step'  => 2,
            'old'   => $data,
            'errors'=> [],
        ], 'layouts/install');
    }

    public function saveDatabase(): void
    {
        $this->guardInstallable();
        csrf_verify();

        $db = [
            'db_host'     => clean((string) input('db_host', 'localhost')),
            'db_port'     => (int) input('db_port', 3306),
            'db_database' => clean((string) input('db_database', '')),
            'db_username' => clean((string) input('db_username', '')),
            'db_password' => (string) input('db_password', ''),
        ];

        $errors = [];
        if ($db['db_database'] === '') {
            $errors['db_database'] = 'Informe o nome do banco.';
        }
        if ($db['db_username'] === '') {
            $errors['db_username'] = 'Informe o usuário do banco.';
        }
        if ($db['db_password'] === '') {
            $errors['db_password'] = 'Informe a senha do banco.';
        }

        $test = ['success' => false, 'message' => ''];
        if ($errors === []) {
            $test = $this->installer->testDatabaseConnection([
                'host'     => $db['db_host'],
                'port'     => $db['db_port'],
                'database' => $db['db_database'],
                'username' => $db['db_username'],
                'password' => $db['db_password'],
            ]);
            if (!$test['success']) {
                $errors['db_password'] = $test['message'];
            }
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->view('install/database', [
                'title'  => 'Instalação — Banco de dados',
                'step'   => 2,
                'old'    => $db,
                'errors' => $errors,
            ], 'layouts/install');
            return;
        }

        $_SESSION['install']['db'] = $db;
        unset($_SESSION['install']['db']['db_password']);
        $_SESSION['install']['db_password'] = $db['db_password'];

        $this->redirect('/install/system');
    }

    public function testDatabase(): void
    {
        $this->guardInstallable();
        csrf_verify();

        $result = $this->installer->testDatabaseConnection([
            'host'     => clean((string) input('db_host', 'localhost')),
            'port'     => (int) input('db_port', 3306),
            'database' => clean((string) input('db_database', '')),
            'username' => clean((string) input('db_username', '')),
            'password' => (string) input('db_password', ''),
        ]);

        $this->json($result, $result['success'] ? 200 : 422);
    }

    public function system(): void
    {
        $this->guardInstallable();
        $this->requireStep('db');

        $old = $this->sessionData()['system'] ?? $this->defaultSystem();
        if (empty($old['lead_endpoint_secret'])) {
            $old['lead_endpoint_secret'] = $this->installer->generateLeadSecret();
            $_SESSION['install']['system']['lead_endpoint_secret'] = $old['lead_endpoint_secret'];
        }

        $this->view('install/system', [
            'title' => 'Instalação — Sistema',
            'step'  => 3,
            'old'   => $old,
            'errors'=> [],
        ], 'layouts/install');
    }

    public function saveSystem(): void
    {
        $this->guardInstallable();
        csrf_verify();
        $this->requireStep('db');

        $data = [
            'app_name'              => clean((string) input('app_name', 'Dança Carajás Captação')),
            'app_url'               => rtrim(clean((string) input('app_url', '')), '/'),
            'app_env'               => clean((string) input('app_env', 'production')),
            'app_debug'             => input('app_debug') !== null ? 1 : 0,
            'lead_endpoint_enabled' => input('lead_endpoint_enabled') !== null ? 1 : 0,
            'lead_endpoint_secret'  => trim((string) input('lead_endpoint_secret', '')),
        ];

        $errors = [];
        if ($data['app_url'] === '') {
            $errors['app_url'] = 'Informe a URL do sistema.';
        }
        if ($data['lead_endpoint_secret'] === '') {
            $data['lead_endpoint_secret'] = $this->installer->generateLeadSecret();
        }
        if ($data['app_env'] === 'production' && $data['app_debug']) {
            $errors['app_debug'] = 'APP_DEBUG deve ficar desligado em produção.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->view('install/system', [
                'title'  => 'Instalação — Sistema',
                'step'   => 3,
                'old'    => $data,
                'errors' => $errors,
            ], 'layouts/install');
            return;
        }

        $_SESSION['install']['system'] = $data;
        $this->redirect('/install/admin');
    }

    public function regenerateToken(): void
    {
        $this->guardInstallable();
        csrf_verify();

        $token = $this->installer->generateLeadSecret();
        $_SESSION['install']['system']['lead_endpoint_secret'] = $token;

        $this->json([
            'success' => true,
            'token'   => $token,
            'masked'  => $this->installer->maskToken($token),
        ]);
    }

    public function admin(): void
    {
        $this->guardInstallable();
        $this->requireStep('system');

        $this->view('install/admin', [
            'title'  => 'Instalação — Administrador',
            'step'   => 4,
            'old'    => $this->sessionData()['admin'] ?? ['admin_name' => '', 'admin_email' => ''],
            'errors' => [],
        ], 'layouts/install');
    }

    public function saveAdmin(): void
    {
        $this->guardInstallable();
        csrf_verify();
        $this->requireStep('system');

        $data = [
            'admin_name'            => clean((string) input('admin_name', '')),
            'admin_email'           => strtolower(trim((string) input('admin_email', ''))),
            'admin_password'        => (string) input('admin_password', ''),
            'admin_password_confirm'=> (string) input('admin_password_confirm', ''),
        ];

        $errors = [];
        if ($data['admin_name'] === '') {
            $errors['admin_name'] = 'Informe o nome do administrador.';
        }
        if ($data['admin_email'] === '' || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Informe um e-mail válido.';
        }
        if (strlen($data['admin_password']) < 8) {
            $errors['admin_password'] = 'A senha deve ter ao menos 8 caracteres.';
        }
        if ($data['admin_password'] !== $data['admin_password_confirm']) {
            $errors['admin_password_confirm'] = 'As senhas não coincidem.';
        }

        if ($errors !== []) {
            http_response_code(422);
            unset($data['admin_password'], $data['admin_password_confirm']);
            $this->view('install/admin', [
                'title'  => 'Instalação — Administrador',
                'step'   => 4,
                'old'    => $data,
                'errors' => $errors,
            ], 'layouts/install');
            return;
        }

        $_SESSION['install']['admin'] = [
            'admin_name'  => $data['admin_name'],
            'admin_email' => $data['admin_email'],
        ];
        $_SESSION['install']['admin_password'] = $data['admin_password'];

        $this->redirect('/install/review');
    }

    public function review(): void
    {
        $this->guardInstallable();
        $this->requireStep('admin');

        $session = $this->sessionData();
        $token   = (string) ($session['system']['lead_endpoint_secret'] ?? '');

        $this->view('install/review', [
            'title'       => 'Instalação — Revisão',
            'step'        => 5,
            'summary'     => [
                'app_name'    => $session['system']['app_name'] ?? '',
                'app_url'     => $session['system']['app_url'] ?? '',
                'app_env'     => $session['system']['app_env'] ?? '',
                'app_debug'   => !empty($session['system']['app_debug']),
                'db_host'     => $session['db']['db_host'] ?? '',
                'db_port'     => $session['db']['db_port'] ?? '',
                'db_database' => $session['db']['db_database'] ?? '',
                'db_username' => $session['db']['db_username'] ?? '',
                'admin_email' => $session['admin']['admin_email'] ?? '',
                'lead_token'  => $this->installer->maskToken($token),
            ],
            'env_exists'  => $this->installer->envExists(),
            'db_has_tables' => $this->installer->databaseHasCriticalTables($this->dbConfigFromSession()),
        ], 'layouts/install');
    }

    public function run(): void
    {
        $this->guardInstallable();
        csrf_verify();
        $this->requireStep('admin');

        $session = $this->sessionData();
        $payload = array_merge(
            $session['db'] ?? [],
            $session['system'] ?? [],
            $session['admin'] ?? [],
            [
                'db_password'            => (string) ($_SESSION['install']['db_password'] ?? ''),
                'admin_password'         => (string) ($_SESSION['install']['admin_password'] ?? ''),
                'confirm_overwrite_env'  => input('confirm_overwrite_env') !== null ? 1 : 0,
                'confirm_existing_db'    => input('confirm_existing_db') !== null ? 1 : 0,
            ]
        );

        try {
            $this->installer->runInstallation($payload);
            unset($_SESSION['install'], $_SESSION['install']['db_password'], $_SESSION['install']['admin_password']);
            $_SESSION['install_complete'] = true;
            $this->redirect('/install/complete');
        } catch (Throwable $e) {
            error_log('[INSTALL] ' . $e->getMessage());
            flash('error', $e->getMessage());
            $this->redirect('/install/review');
        }
    }

    public function complete(): void
    {
        if (!$this->installer->isInstalled() && empty($_SESSION['install_complete'])) {
            $this->redirect('/install');
        }
        unset($_SESSION['install_complete']);

        $this->view('install/complete', [
            'title' => 'Instalação concluída',
            'step'  => 6,
        ], 'layouts/install');
    }

    public function blocked(): void
    {
        http_response_code(403);
        $this->view('install/blocked', [
            'title' => 'Instalador bloqueado',
        ], 'layouts/install');
    }

    private function guardInstallable(): void
    {
        if ($this->installer->isInstalled()) {
            $this->blocked();
            exit;
        }
    }

    private function requireStep(string $key): void
    {
        if (empty($this->sessionData()[$key])) {
            $this->redirect('/install');
        }
    }

    /** @return array<string, mixed> */
    private function sessionData(): array
    {
        return is_array($_SESSION['install'] ?? null) ? $_SESSION['install'] : [];
    }

    /** @return array<string, mixed> */
    private function defaultDb(): array
    {
        return [
            'db_host'     => 'localhost',
            'db_port'     => 3306,
            'db_database' => 'u482227589_comercialdcf',
            'db_username' => 'u482227589_comercialdcf',
            'db_password' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function defaultSystem(): array
    {
        return [
            'app_name'              => 'Dança Carajás Captação',
            'app_url'               => 'https://comercial.dancacarajas.com.br',
            'app_env'               => 'production',
            'app_debug'             => 0,
            'lead_endpoint_enabled' => 1,
            'lead_endpoint_secret'  => '',
        ];
    }

    /** @return array{host:string,port:int,database:string,username:string,password:string} */
    private function dbConfigFromSession(): array
    {
        $s = $this->sessionData();

        return [
            'host'     => (string) ($s['db']['db_host'] ?? 'localhost'),
            'port'     => (int) ($s['db']['db_port'] ?? 3306),
            'database' => (string) ($s['db']['db_database'] ?? ''),
            'username' => (string) ($s['db']['db_username'] ?? ''),
            'password' => (string) ($_SESSION['install']['db_password'] ?? ''),
        ];
    }
}
