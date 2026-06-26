<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Serviço de instalação web (Etapa 9B — produção Hostinger).
 */
final class InstallerService
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 2);
    }

    public function lockFilePath(): string
    {
        return $this->basePath . '/storage/installed.lock';
    }

    public function schemaFilePath(): string
    {
        return $this->basePath . '/database/install_schema.sql';
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockFilePath());
    }

    public function envExists(): bool
    {
        return is_file($this->basePath . '/.env');
    }

    /**
     * @return array<int, array{label:string,ok:bool,required:bool,hint:string}>
     */
    public function checkRequirements(): array
    {
        $checks = [];

        $checks[] = $this->req(
            'PHP 8.2 ou superior',
            version_compare(PHP_VERSION, '8.2.0', '>='),
            true,
            'Versão atual: ' . PHP_VERSION
        );
        $checks[] = $this->req('Extensão PDO', extension_loaded('pdo'), true, 'Instale php-pdo no servidor.');
        $checks[] = $this->req(
            'Extensão pdo_mysql',
            extension_loaded('pdo_mysql'),
            true,
            'Necessária para MySQL/MariaDB.'
        );
        $checks[] = $this->req(
            'Permissão para criar .env',
            is_writable($this->basePath) || (!is_file($this->basePath . '/.env') && is_writable(dirname($this->basePath))),
            true,
            'A pasta raiz do projeto precisa permitir escrita do .env.'
        );

        foreach ([
            'storage/logs'     => 'Logs do sistema',
            'storage/uploads'  => 'Uploads privados',
            'storage/exports'  => 'Exportações',
            'storage/backups'  => 'Backups',
            'storage/ratelimit'=> 'Rate limit do endpoint de leads',
        ] as $dir => $label) {
            $path = $this->basePath . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $checks[] = $this->req(
                "Escrita em {$dir}",
                is_dir($path) && is_writable($path),
                true,
                $label
            );
        }

        $checks[] = $this->req(
            'Arquivo database/install_schema.sql',
            is_file($this->schemaFilePath()) && is_readable($this->schemaFilePath()),
            true,
            'Schema completo até a Etapa 9.'
        );
        $checks[] = $this->req(
            'Instalador ainda não bloqueado',
            !$this->isInstalled(),
            true,
            'Remova storage/installed.lock apenas para reinstalar manualmente.'
        );

        return $checks;
    }

    public function requirementsPassed(): bool
    {
        foreach ($this->checkRequirements() as $c) {
            if ($c['required'] && !$c['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{host:string,port:int|string,database:string,username:string,password:string} $db
     */
    public function testDatabaseConnection(array $db): array
    {
        try {
            $pdo = $this->makePdo($db, false);
            $pdo->query('SELECT 1');

            return ['success' => true, 'message' => 'Conexão estabelecida com sucesso.'];
        } catch (PDOException $e) {
            error_log('[INSTALL] Falha teste DB: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Não foi possível conectar ao banco. Verifique host, usuário e senha.'];
        }
    }

    /**
     * @param array{host:string,port:int|string,database:string,username:string,password:string} $db
     */
    public function databaseHasCriticalTables(array $db): bool
    {
        try {
            $pdo = $this->makePdo($db, true);
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (PDOException) {
            return false;
        }
    }

    public function generateLeadSecret(): string
    {
        return 'DCF_2026_' . bin2hex(random_bytes(32));
    }

    public function generateAppKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 16) {
            return str_repeat('*', $len);
        }

        return substr($token, 0, 12) . str_repeat('*', max(4, $len - 20)) . substr($token, -4);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function writeEnvFile(array $data, bool $force = false): void
    {
        $path = $this->basePath . '/.env';
        if (is_file($path) && !$force) {
            throw new RuntimeException('O arquivo .env já existe. Confirme a sobrescrita para continuar.');
        }

        $lines = [
            '# Gerado pelo instalador web — ' . date('c'),
            '',
            'APP_NAME="' . $this->envQuote((string) ($data['app_name'] ?? 'Dança Carajás Captação')) . '"',
            'APP_ENV=' . ($data['app_env'] ?? 'production'),
            'APP_DEBUG=' . (($data['app_debug'] ?? false) ? 'true' : 'false'),
            'APP_URL=' . rtrim((string) ($data['app_url'] ?? ''), '/'),
            'APP_TIMEZONE=America/Belem',
            'APP_LOCALE=pt_BR',
            'APP_KEY=' . ($data['app_key'] ?? $this->generateAppKey()),
            '',
            'SESSION_NAME=DCC_SESSION',
            'SESSION_LIFETIME=7200',
            'SESSION_SECURE=true',
            'SESSION_SAMESITE=Lax',
            'SESSION_REGENERATE=900',
            'SESSION_TIMEOUT=7200',
            '',
            'DB_DRIVER=mysql',
            'DB_HOST=' . ($data['db_host'] ?? 'localhost'),
            'DB_PORT=' . ($data['db_port'] ?? '3306'),
            'DB_DATABASE=' . ($data['db_database'] ?? ''),
            'DB_USERNAME=' . ($data['db_username'] ?? ''),
            'DB_PASSWORD=' . $this->envQuote((string) ($data['db_password'] ?? '')),
            'DB_CHARSET=utf8mb4',
            'DB_COLLATION=utf8mb4_unicode_ci',
            '',
            'LEAD_ENDPOINT_ENABLED=' . (!empty($data['lead_endpoint_enabled']) ? 'true' : 'false'),
            'LEAD_ENDPOINT_SECRET=' . ($data['lead_endpoint_secret'] ?? ''),
            'LEAD_RATE_LIMIT_MINUTES=10',
            'LEAD_RATE_LIMIT_MAX_ATTEMPTS=5',
            '',
            'MAIL_DRIVER=smtp',
            'MAIL_HOST=smtp.hostinger.com',
            'MAIL_PORT=465',
            'MAIL_ENCRYPTION=ssl',
            'MAIL_USERNAME=',
            'MAIL_PASSWORD=',
            'MAIL_FROM_ADDRESS=no-reply@dancacarajas.com.br',
            'MAIL_FROM_NAME="Dança Carajás Captação"',
        ];

        if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo .env.');
        }

        @chmod($path, 0640);
    }

    /**
     * @param array{host:string,port:int|string,database:string,username:string,password:string} $db
     */
    public function importSchema(array $db): void
    {
        $pdo = $this->makePdo($db, true);
        $sql = file_get_contents($this->schemaFilePath());
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('install_schema.sql vazio ou ilegível.');
        }

        $this->executeSqlBatch($pdo, $sql);
    }

    /**
     * @param array{host:string,port:int|string,database:string,username:string,password:string} $db
     */
    public function createAdministrator(array $db, string $name, string $email, string $password): int
    {
        $pdo = $this->makePdo($db, true);

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn()) {
            throw new RuntimeException('Já existe um usuário com este e-mail no banco.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, status, must_change_password, created_at, updated_at)
             VALUES (:name, :email, :hash, \'active\', 0, NOW(), NOW())'
        );
        $stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash]);
        $userId = (int) $pdo->lastInsertId();

        $role = $pdo->query("SELECT id FROM roles WHERE slug = 'administrador-geral' LIMIT 1")->fetchColumn();
        if (!$role) {
            throw new RuntimeException('Perfil Administrador Geral não encontrado após importação.');
        }

        $link = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        $link->execute(['u' => $userId, 'r' => (int) $role]);

        return $userId;
    }

    public function createLockFile(): void
    {
        $dir = dirname($this->lockFilePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $content = json_encode([
            'installed_at' => date('c'),
            'php'          => PHP_VERSION,
            'app_url'      => env('APP_URL', ''),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (file_put_contents($this->lockFilePath(), $content . PHP_EOL) === false) {
            throw new RuntimeException('Não foi possível criar storage/installed.lock.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function runInstallation(array $payload): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('Sistema já instalado.');
        }

        $db = [
            'host'     => (string) ($payload['db_host'] ?? 'localhost'),
            'port'     => (int) ($payload['db_port'] ?? 3306),
            'database' => (string) ($payload['db_database'] ?? ''),
            'username' => (string) ($payload['db_username'] ?? ''),
            'password' => (string) ($payload['db_password'] ?? ''),
        ];

        $test = $this->testDatabaseConnection($db);
        if (!$test['success']) {
            throw new RuntimeException($test['message']);
        }

        $confirmExisting = !empty($payload['confirm_existing_db']);
        if ($this->databaseHasCriticalTables($db) && !$confirmExisting) {
            throw new RuntimeException('O banco já contém tabelas. Marque a confirmação para continuar.');
        }

        if ($this->envExists() && empty($payload['confirm_overwrite_env'])) {
            throw new RuntimeException('O arquivo .env já existe. Confirme a sobrescrita.');
        }

        if ((bool) ($payload['app_debug'] ?? false) && ($payload['app_env'] ?? '') === 'production') {
            throw new RuntimeException('APP_DEBUG não pode estar ativo em produção.');
        }

        $this->writeEnvFile([
            'app_name'              => $payload['app_name'] ?? 'Dança Carajás Captação',
            'app_url'               => $payload['app_url'] ?? '',
            'app_env'               => $payload['app_env'] ?? 'production',
            'app_debug'             => $payload['app_debug'] ?? false,
            'app_key'               => $payload['app_key'] ?? $this->generateAppKey(),
            'db_host'               => $db['host'],
            'db_port'               => $db['port'],
            'db_database'           => $db['database'],
            'db_username'           => $db['username'],
            'db_password'           => $db['password'],
            'lead_endpoint_enabled' => $payload['lead_endpoint_enabled'] ?? true,
            'lead_endpoint_secret'  => $payload['lead_endpoint_secret'] ?? $this->generateLeadSecret(),
        ], !empty($payload['confirm_overwrite_env']));

        load_env($this->basePath . '/.env');

        $this->importSchema($db);

        $this->createAdministrator(
            $db,
            (string) ($payload['admin_name'] ?? ''),
            strtolower(trim((string) ($payload['admin_email'] ?? ''))),
            (string) ($payload['admin_password'] ?? '')
        );

        $this->createLockFile();

        $logLine = date('c') . ' Installation completed for ' . ($payload['app_url'] ?? 'unknown') . PHP_EOL;
        @file_put_contents($this->basePath . '/storage/logs/install.log', $logLine, FILE_APPEND);
    }

    /**
     * @param array{host:string,port:int|string,database:string,username:string,password:string} $db
     */
    private function makePdo(array $db, bool $withDatabase): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4%s',
            $db['host'],
            (int) $db['port'],
            $withDatabase ? ';dbname=' . $db['database'] : ''
        );

        return new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    private function executeSqlBatch(PDO $pdo, string $sql): void
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    /**
     * @return array{label:string,ok:bool,required:bool,hint:string}
     */
    private function req(string $label, bool $ok, bool $required, string $hint): array
    {
        return compact('label', 'ok', 'required', 'hint');
    }

    private function envQuote(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\'=]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
