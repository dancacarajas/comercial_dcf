<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Kernel da aplicacao.
 *
 * Responsavel por inicializar autoload, ambiente, sessao segura,
 * tratamento de erros e despachar as rotas. Tudo sem dependencias
 * externas, compativel com hospedagem compartilhada.
 */
final class App
{
    public string $basePath;

    /** @var array<string, mixed> */
    public array $config = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function boot(): void
    {
        $this->registerAutoloader();
        $this->loadHelpers();
        $this->loadConfig();
        $this->configureErrors();
        $this->configureTimezone();
        $this->startSession();
    }

    /**
     * Autoloader PSR-4 simples para o namespace App\.
     */
    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix  = 'App\\';
            $baseDir = $this->basePath . '/app/';

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }

    private function loadHelpers(): void
    {
        // env() precisa existir antes de carregar as configs.
        require_once $this->basePath . '/app/Helpers/env.php';
        load_env($this->basePath . '/.env');

        require_once $this->basePath . '/app/Helpers/security.php';
        require_once $this->basePath . '/app/Helpers/csrf.php';
        require_once $this->basePath . '/app/Helpers/charts.php';
    }

    private function loadConfig(): void
    {
        $this->config['app']      = require $this->basePath . '/config/app.php';
        $this->config['database'] = require $this->basePath . '/config/database.php';
        $this->config['mail']     = require $this->basePath . '/config/mail.php';
    }

    private function configureErrors(): void
    {
        $debug   = (bool) ($this->config['app']['debug'] ?? false);
        $logFile = $this->basePath . '/storage/logs/app.log';

        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        if ($debug) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        }

        set_exception_handler(function (Throwable $e) use ($debug): void {
            error_log('[EXCEPTION] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);

            if ($debug) {
                echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo View::render('errors/500', [], 'layouts/admin');
            }
        });

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    private function configureTimezone(): void
    {
        $tz = (string) ($this->config['app']['timezone'] ?? 'UTC');
        date_default_timezone_set($tz);
    }

    /**
     * Inicia uma sessao com cookies seguros e regeneracao periodica de ID.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $s      = $this->config['app']['session'] ?? [];
        $secure = (bool) ($s['secure'] ?? true);

        // Se nao estiver em HTTPS, nao forca cookie seguro (evita quebrar local).
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => (int) ($s['lifetime'] ?? 7200),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure && $https,
            'httponly' => (bool) ($s['httponly'] ?? true),
            'samesite' => (string) ($s['samesite'] ?? 'Lax'),
        ]);

        session_name((string) ($s['name'] ?? 'DCC_SESSION'));
        session_start();

        // Regeneracao periodica do ID para mitigar fixation.
        $interval = (int) ($s['regenerate_each'] ?? 900);
        $now      = time();

        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = $now;
        } elseif ($now - (int) $_SESSION['_created_at'] > $interval) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = $now;
        }

        // Timeout por inatividade: encerra sessoes autenticadas ociosas.
        $timeout = (int) ($this->config['app']['session_timeout'] ?? 7200);

        if (!empty($_SESSION['user_id']) && $timeout > 0) {
            $last = (int) ($_SESSION['_last_activity'] ?? $now);

            if ($now - $last > $timeout) {
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['_flash_timeout'] = true;
            }
        }

        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Carrega as rotas e despacha a requisicao atual.
     */
    public function run(): void
    {
        $router = new Router();

        $register = require $this->basePath . '/routes/web.php';
        $register($router);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        $router->dispatch($method, $uri);
    }
}
