<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Configuracao SMTP transacional.
 */
final class MailSetting extends Model
{
    protected string $table = 'mail_settings';

    /** @return array<string, string> */
    public function providers(): array
    {
        return [
            'disabled' => 'Desativado',
            'gmail' => 'Gmail SMTP',
            'smtp_custom' => 'SMTP customizado',
        ];
    }

    /** @return array<string, string> */
    public function encryptions(): array
    {
        return [
            'tls' => 'TLS',
            'ssl' => 'SSL',
            'none' => 'Sem criptografia',
        ];
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $row = $this->query('SELECT * FROM `mail_settings` ORDER BY `id` ASC LIMIT 1')->fetch();
        if ($row !== false) {
            return $row;
        }

        $this->query(
            "INSERT INTO `mail_settings`
                (`provider`, `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_username`,
                 `from_name`, `from_email`, `reply_to_name`, `reply_to_email`, `enabled`, `dry_run`,
                 `hourly_limit`, `daily_limit`, `created_at`, `updated_at`)
             VALUES
                ('gmail', 'smtp.gmail.com', 587, 'tls', 'dancacarajas@gmail.com',
                 'Danca Carajas Captacao', 'dancacarajas@gmail.com', 'Equipe Danca Carajas', 'dancacarajas@gmail.com',
                 0, 1, 20, 100, NOW(), NOW())"
        );

        return $this->current();
    }

    /** @return array<string, string> */
    public function validate(array $data, bool $hasStoredPassword): array
    {
        $errors = [];
        if (!array_key_exists((string) ($data['provider'] ?? ''), $this->providers())) {
            $errors['provider'] = 'Selecione um provedor valido.';
        }
        if ((string) ($data['provider'] ?? '') !== 'disabled') {
            foreach (['smtp_host', 'smtp_username', 'from_name', 'from_email'] as $field) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $errors[$field] = 'Campo obrigatorio.';
                }
            }
            if (!filter_var((string) ($data['from_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $errors['from_email'] = 'E-mail remetente invalido.';
            }
            $reply = trim((string) ($data['reply_to_email'] ?? ''));
            if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
                $errors['reply_to_email'] = 'E-mail de resposta invalido.';
            }
            if ((int) ($data['smtp_port'] ?? 0) <= 0) {
                $errors['smtp_port'] = 'Porta invalida.';
            }
            if (!array_key_exists((string) ($data['smtp_encryption'] ?? ''), $this->encryptions())) {
                $errors['smtp_encryption'] = 'Criptografia invalida.';
            }
            if (!$hasStoredPassword && trim((string) ($data['smtp_password'] ?? '')) === '') {
                $errors['smtp_password'] = 'Informe a senha de app/SMTP.';
            }
        }
        if ((int) ($data['hourly_limit'] ?? 0) < 0 || (int) ($data['daily_limit'] ?? 0) < 0) {
            $errors['limits'] = 'Limites devem ser zero ou positivos.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    public function saveSettings(array $data): void
    {
        $current = $this->current();
        $password = trim((string) ($data['smtp_password'] ?? ''));
        $encrypted = (string) ($current['smtp_password_encrypted'] ?? '');
        if ($password !== '') {
            $encrypted = $this->encryptSecret($password);
        }

        $this->query(
            'UPDATE `mail_settings`
                SET `provider` = :provider,
                    `smtp_host` = :smtp_host,
                    `smtp_port` = :smtp_port,
                    `smtp_encryption` = :smtp_encryption,
                    `smtp_username` = :smtp_username,
                    `smtp_password_encrypted` = :smtp_password_encrypted,
                    `from_name` = :from_name,
                    `from_email` = :from_email,
                    `reply_to_name` = :reply_to_name,
                    `reply_to_email` = :reply_to_email,
                    `enabled` = :enabled,
                    `dry_run` = :dry_run,
                    `hourly_limit` = :hourly_limit,
                    `daily_limit` = :daily_limit,
                    `updated_at` = NOW()
              WHERE `id` = :id',
            [
                'provider' => (string) $data['provider'],
                'smtp_host' => (string) $data['smtp_host'],
                'smtp_port' => (int) $data['smtp_port'],
                'smtp_encryption' => (string) $data['smtp_encryption'],
                'smtp_username' => (string) $data['smtp_username'],
                'smtp_password_encrypted' => $encrypted !== '' ? $encrypted : null,
                'from_name' => (string) $data['from_name'],
                'from_email' => (string) $data['from_email'],
                'reply_to_name' => (string) ($data['reply_to_name'] ?? ''),
                'reply_to_email' => (string) ($data['reply_to_email'] ?? ''),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'dry_run' => !empty($data['dry_run']) ? 1 : 0,
                'hourly_limit' => (int) ($data['hourly_limit'] ?? 0),
                'daily_limit' => (int) ($data['daily_limit'] ?? 0),
                'id' => (int) $current['id'],
            ]
        );
    }

    public function decryptedPassword(array $settings): string
    {
        $encrypted = (string) ($settings['smtp_password_encrypted'] ?? '');
        return $encrypted !== '' ? $this->decryptSecret($encrypted) : '';
    }

    public function markTest(string $status, ?string $message = null): void
    {
        $current = $this->current();
        $this->query(
            'UPDATE `mail_settings`
                SET `last_tested_at` = NOW(), `last_test_status` = :status, `last_test_message` = :message, `updated_at` = NOW()
              WHERE `id` = :id',
            ['status' => $status, 'message' => $message, 'id' => (int) $current['id']]
        );
    }

    private function encryptSecret(string $plain): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $this->cryptoKey(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Nao foi possivel criptografar a senha SMTP.');
        }

        return base64_encode($iv . $cipher);
    }

    private function decryptSecret(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= 16) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $this->cryptoKey(), OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : '';
    }

    private function cryptoKey(): string
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        return hash('sha256', (string) ($config['key'] ?? 'mail-settings-key'), true);
    }
}
