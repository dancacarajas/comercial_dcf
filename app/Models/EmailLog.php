<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EmailLog extends Model
{
    protected string $table = 'email_logs';

    /** @param array<string, mixed> $data */
    public function record(array $data): int
    {
        $this->query(
            'INSERT INTO `email_logs`
                (`event_key`, `recipient_email`, `recipient_name`, `subject`, `status`, `error_message`, `payload_json`, `sent_at`, `created_at`)
             VALUES
                (:event_key, :recipient_email, :recipient_name, :subject, :status, :error_message, :payload_json, :sent_at, NOW())',
            [
                'event_key' => (string) ($data['event_key'] ?? ''),
                'recipient_email' => (string) ($data['recipient_email'] ?? ''),
                'recipient_name' => (string) ($data['recipient_name'] ?? ''),
                'subject' => (string) ($data['subject'] ?? ''),
                'status' => (string) ($data['status'] ?? 'pending'),
                'error_message' => $data['error_message'] ?? null,
                'payload_json' => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'sent_at' => $data['sent_at'] ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        return $this->query(
            'SELECT * FROM `email_logs`' . $where . ' ORDER BY `created_at` DESC, `id` DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        return (int) $this->query('SELECT COUNT(*) FROM `email_logs`' . $where, $params)->fetchColumn();
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ' WHERE 1=1';
        $params = [];
        foreach (['status', 'event_key'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where .= ' AND `' . $field . '` = :' . $field;
                $params[$field] = $value;
            }
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (`recipient_email` LIKE :q1 OR `recipient_name` LIKE :q2 OR `subject` LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        return [$where, $params];
    }
}
