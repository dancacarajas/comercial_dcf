<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Camada de conexao com o banco usando PDO.
 *
 * - Conexao unica (singleton) por requisicao.
 * - Sempre com prepared statements (EMULATE_PREPARES = false).
 * - Lanca excecoes em caso de erro (tratadas pelo App).
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
        // Impede instanciacao direta.
    }

    /**
     * Injeta uma conexao PDO (uso em testes/integracao).
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Reseta a conexao (uso em testes).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Retorna a instancia unica de PDO.
     */
    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            // Nao expoe credenciais; registra e lanca erro generico.
            error_log('[DB] Falha de conexao: ' . $e->getMessage());
            throw new RuntimeException('Nao foi possivel conectar ao banco de dados.', 0, $e);
        }

        $collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');
        self::$instance->exec('SET NAMES utf8mb4 COLLATE ' . $collation);
        self::$instance->exec("SET time_zone = '+00:00'");

        return self::$instance;
    }

    /**
     * Executa uma query preparada e retorna o statement.
     *
     * @param array<int|string, mixed> $params
     */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }
}
