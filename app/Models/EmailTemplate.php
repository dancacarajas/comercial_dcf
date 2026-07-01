<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EmailTemplate extends Model
{
    protected string $table = 'email_templates';

    /** @return array<int, array<string, mixed>> */
    public function paginate(int $page = 1, int $perPage = 30): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        return $this->query(
            'SELECT * FROM `email_templates` ORDER BY `event_key` ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        )->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `email_templates`')->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findByEvent(string $eventKey): ?array
    {
        $row = $this->query(
            'SELECT * FROM `email_templates` WHERE `event_key` = :event_key LIMIT 1',
            ['event_key' => $eventKey]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function upsert(array $data): void
    {
        $this->query(
            'INSERT INTO `email_templates`
                (`event_key`, `name`, `subject`, `body_text`, `body_html`, `enabled`, `created_at`, `updated_at`)
             VALUES
                (:event_key, :name, :subject, :body_text, :body_html, :enabled, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`),
                `subject` = VALUES(`subject`),
                `body_text` = VALUES(`body_text`),
                `body_html` = VALUES(`body_html`),
                `enabled` = VALUES(`enabled`),
                `updated_at` = NOW()',
            [
                'event_key' => (string) $data['event_key'],
                'name' => (string) $data['name'],
                'subject' => (string) $data['subject'],
                'body_text' => (string) ($data['body_text'] ?? ''),
                'body_html' => (string) ($data['body_html'] ?? ''),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
            ]
        );
    }
}
