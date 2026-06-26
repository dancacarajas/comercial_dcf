<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Model base.
 *
 * Fornece acesso ao PDO e helpers genericos sempre com prepared
 * statements. Models concretos definem a propriedade $table.
 */
abstract class Model
{
    protected PDO $db;

    /** Nome da tabela do model concreto. */
    protected string $table = '';

    /** Chave primaria. */
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Executa SQL arbitrario com bind seguro.
     *
     * @param array<int|string, mixed> $params
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Retorna todos os registros da tabela.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->query("SELECT * FROM `{$this->table}`")->fetchAll();
    }

    /**
     * Busca um registro pela chave primaria.
     *
     * @return array<string, mixed>|null
     */
    public function find(int|string $id): ?array
    {
        $stmt = $this->query(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1",
            ['id' => $id]
        );

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Busca o primeiro registro que casa com uma coluna/valor.
     *
     * @return array<string, mixed>|null
     */
    public function findBy(string $column, mixed $value): ?array
    {
        // Coluna validada por allowlist simples (evita injecao no identificador).
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';

        $stmt = $this->query(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value LIMIT 1",
            ['value' => $value]
        );

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Insere um registro e retorna o ID gerado.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $escaped = array_map(static fn (string $c): string => '`' . $c . '`', $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $escaped),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return $this->db->lastInsertId();
    }
}
