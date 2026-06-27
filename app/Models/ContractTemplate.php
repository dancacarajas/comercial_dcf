<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Helpers\ContractDocumentHelper;

final class ContractTemplate extends Model
{
    protected string $table = 'contract_templates';

    private const FILLABLE = [
        'template_key', 'title', 'description', 'template_type', 'status',
        'content_html', 'content_text', 'available_placeholders_json',
        'default_signer_role', 'version', 'is_default', 'created_by', 'updated_by',
    ];

    /** @return array<string, string> */
    public function getTypes(): array
    {
        return [
            'autorizacao_captador'  => 'Autorização captador',
            'contrato_captador'     => 'Contrato captador',
            'termo_confidencialidade'=> 'Termo confidencialidade',
            'termo_patrocinio'      => 'Termo patrocínio',
            'termo_apoio'           => 'Termo apoio',
            'termo_permuta'         => 'Termo permuta',
            'contrato_patrocinio'   => 'Contrato patrocínio',
            'outro'                 => 'Outro',
        ];
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'rascunho'  => 'Rascunho',
            'ativo'     => 'Ativo',
            'inativo'   => 'Inativo',
            'arquivado' => 'Arquivado',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT * FROM `contract_templates` WHERE {$where} ORDER BY `title` ASC LIMIT {$perPage} OFFSET {$offset}";

        return $this->query($sql, $params)->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);

        return (int) ($this->query("SELECT COUNT(*) FROM `contract_templates` WHERE {$where}", $params)->fetchColumn() ?: 0);
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query('SELECT * FROM `contract_templates` WHERE `id` = :id LIMIT 1', ['id' => $id])->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findDefaultForType(string $type): ?array
    {
        $row = $this->query(
            "SELECT * FROM `contract_templates`
              WHERE `template_type` = :t AND `status` = 'ativo' AND `archived_at` IS NULL
              ORDER BY `is_default` DESC, `id` DESC LIMIT 1",
            ['t' => $type]
        )->fetch();

        return $row !== false ? ContractDocumentHelper::normalizeTemplate($row) : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->filterFillable($data);
        if (empty($payload['status'])) {
            $payload['status'] = 'rascunho';
        }
        $cols = array_keys($payload);
        $this->query(
            'INSERT INTO `contract_templates` (`' . implode('`, `', $cols) . '`, `created_at`) VALUES (' .
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols)) . ', NOW())',
            $payload
        );

        return (string) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): void
    {
        $payload = $this->filterFillable($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        if ($payload === []) {
            return;
        }
        $sets = [];
        foreach (array_keys($payload) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $payload['id'] = $id;
        $this->query('UPDATE `contract_templates` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $payload);
    }

    public function archive(int|string $id, int|string|null $userId = null): void
    {
        $this->query(
            'UPDATE `contract_templates` SET `archived_at` = NOW(), `status` = :st, `updated_by` = :uid, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id, 'st' => 'arquivado', 'uid' => $userId]
        );
    }

    public function restore(int|string $id, int|string|null $userId = null): void
    {
        $this->query(
            'UPDATE `contract_templates` SET `archived_at` = NULL, `status` = :st, `updated_by` = :uid, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id, 'st' => 'ativo', 'uid' => $userId]
        );
    }

    /** @param array<string, mixed> $data */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];
        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'] = 'Informe o título.';
        }
        if (trim(strip_tags((string) ($data['content_html'] ?? ''))) === '') {
            $errors['content_html'] = 'Informe o conteúdo do modelo.';
        }
        $type = (string) ($data['template_type'] ?? '');
        if ($type === '' || !array_key_exists($type, $this->getTypes())) {
            $errors['template_type'] = 'Tipo inválido.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];
        if (empty($filters['show_archived'])) {
            $conditions[] = '`archived_at` IS NULL';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(`title` LIKE :q OR `template_key` LIKE :q OR `description` LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        foreach (['status', 'template_type'] as $key) {
            $val = trim((string) ($filters[$key] ?? ''));
            if ($val !== '') {
                $conditions[] = '`' . $key . '` = :' . $key;
                $params[$key] = $val;
            }
        }

        return [implode(' AND ', $conditions), $params];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function filterFillable(array $data): array
    {
        $out = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $out[$col] = $data[$col];
            }
        }

        return $out;
    }
}
