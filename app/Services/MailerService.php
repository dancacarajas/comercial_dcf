<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\EmailLog;
use App\Models\MailSetting;

/**
 * Envio transacional SMTP com dry-run seguro por padrao.
 */
final class MailerService
{
    /** @return array{status:string,message:string,log_id:int,outbox_id:int|null} */
    public function sendTest(string $toEmail, string $toName = ''): array
    {
        return $this->send([
            'event_key' => 'email_settings_test',
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => 'Teste de e-mail transacional - Danca Carajas',
            'body_text' => "Este e-mail confirma que a configuracao transacional foi acionada.\n\nSe dry-run estiver ativo, nenhum envio real foi feito.",
            'body_html' => '<p>Este e-mail confirma que a configuracao transacional foi acionada.</p><p>Se dry-run estiver ativo, nenhum envio real foi feito.</p>',
            'payload' => ['source' => 'settings_test'],
        ]);
    }

    /**
     * @param array<string, mixed> $message
     * @return array{status:string,message:string,log_id:int,outbox_id:int|null}
     */
    public function send(array $message): array
    {
        $settingsModel = new MailSetting();
        $settings = $settingsModel->current();
        $eventKey = (string) ($message['event_key'] ?? 'manual');
        $toEmail = trim((string) ($message['to_email'] ?? ''));
        $toName = trim((string) ($message['to_name'] ?? ''));
        $subject = trim((string) ($message['subject'] ?? ''));
        $bodyText = (string) ($message['body_text'] ?? '');
        $bodyHtml = (string) ($message['body_html'] ?? '');
        $payload = (array) ($message['payload'] ?? []);

        $outboxId = $this->createOutbox($eventKey, $toEmail, $toName, $subject, $bodyText, $bodyHtml, $payload);

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->finish($outboxId, $eventKey, $toEmail, $toName, $subject, 'failed', 'Destinatario invalido.', $payload);
        }
        if ((string) ($settings['provider'] ?? '') === 'disabled' || (int) ($settings['enabled'] ?? 0) !== 1) {
            return $this->finish($outboxId, $eventKey, $toEmail, $toName, $subject, 'skipped', 'Envio desativado em mail_settings.', $payload);
        }
        if ((int) ($settings['dry_run'] ?? 1) === 1) {
            return $this->finish($outboxId, $eventKey, $toEmail, $toName, $subject, 'simulated', 'Dry-run ativo: e-mail registrado sem envio real.', $payload, true);
        }

        try {
            $this->sendSmtp($settings, $settingsModel->decryptedPassword($settings), $toEmail, $toName, $subject, $bodyText, $bodyHtml);
            return $this->finish($outboxId, $eventKey, $toEmail, $toName, $subject, 'sent', 'E-mail enviado.', $payload, true);
        } catch (\Throwable $e) {
            return $this->finish($outboxId, $eventKey, $toEmail, $toName, $subject, 'failed', $e->getMessage(), $payload);
        }
    }

    private function sendSmtp(array $settings, string $password, string $toEmail, string $toName, string $subject, string $bodyText, string $bodyHtml): void
    {
        if ($password === '') {
            throw new \RuntimeException('Senha SMTP nao configurada.');
        }
        $host = (string) ($settings['smtp_host'] ?? '');
        $port = (int) ($settings['smtp_port'] ?? 587);
        $encryption = (string) ($settings['smtp_encryption'] ?? 'tls');
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new \RuntimeException('Falha ao conectar ao SMTP: ' . $errstr);
        }
        stream_set_timeout($socket, 20);

        $read = function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $command, array $expect) use ($socket, $read): string {
            fwrite($socket, $command . "\r\n");
            $response = $read();
            $code = (int) substr($response, 0, 3);
            if (!in_array($code, $expect, true)) {
                throw new \RuntimeException('SMTP respondeu ' . trim($response));
            }
            return $response;
        };

        $banner = $read();
        if ((int) substr($banner, 0, 3) !== 220) {
            throw new \RuntimeException('SMTP indisponivel: ' . trim($banner));
        }
        $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        if ($encryption === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Falha ao iniciar TLS SMTP.');
            }
            $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) $settings['smtp_username']), [334]);
        $cmd(base64_encode($password), [235]);

        $fromEmail = (string) ($settings['from_email'] ?? '');
        $fromName = (string) ($settings['from_name'] ?? '');
        $boundary = '=_dcx_' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . $this->address($fromEmail, $fromName),
            'To: ' . $this->address($toEmail, $toName),
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $replyTo = trim((string) ($settings['reply_to_email'] ?? ''));
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $this->address($replyTo, (string) ($settings['reply_to_name'] ?? ''));
        }
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $bodyText . "\r\n";
        if ($bodyHtml !== '') {
            $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . $bodyHtml . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";
        $raw = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        $cmd('MAIL FROM:<' . $fromEmail . '>', [250]);
        $cmd('RCPT TO:<' . $toEmail . '>', [250, 251]);
        $cmd('DATA', [354]);
        fwrite($socket, str_replace("\r\n.", "\r\n..", $raw) . "\r\n.\r\n");
        $response = $read();
        if ((int) substr($response, 0, 3) !== 250) {
            throw new \RuntimeException('SMTP falhou no DATA: ' . trim($response));
        }
        $cmd('QUIT', [221, 250]);
        fclose($socket);
    }

    private function createOutbox(string $eventKey, string $toEmail, string $toName, string $subject, string $bodyText, string $bodyHtml, array $payload): ?int
    {
        try {
            Database::run(
                'INSERT INTO `email_outbox`
                    (`event_key`, `recipient_email`, `recipient_name`, `subject`, `body_text`, `body_html`, `payload_json`, `status`, `created_at`, `updated_at`)
                 VALUES
                    (:event_key, :email, :name, :subject, :body_text, :body_html, :payload, :status, NOW(), NOW())',
                [
                    'event_key' => $eventKey,
                    'email' => $toEmail,
                    'name' => $toName,
                    'subject' => $subject,
                    'body_text' => $bodyText,
                    'body_html' => $bodyHtml,
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'pending',
                ]
            );
            return (int) Database::connection()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{status:string,message:string,log_id:int,outbox_id:int|null} */
    private function finish(?int $outboxId, string $eventKey, string $toEmail, string $toName, string $subject, string $status, string $message, array $payload, bool $sent = false): array
    {
        if ($outboxId !== null) {
            Database::run(
                'UPDATE `email_outbox`
                    SET `status` = :status, `error_message` = :error, `sent_at` = :sent_at, `updated_at` = NOW()
                  WHERE `id` = :id',
                ['status' => $status, 'error' => $status === 'failed' ? $message : null, 'sent_at' => $sent ? date('Y-m-d H:i:s') : null, 'id' => $outboxId]
            );
        }
        // Registra em email_logs via EmailLog para auditoria do envio.
        $logId = (new EmailLog())->record([
            'event_key' => $eventKey,
            'recipient_email' => $toEmail,
            'recipient_name' => $toName,
            'subject' => $subject,
            'status' => $status,
            'error_message' => $status === 'failed' ? $message : null,
            'payload' => $payload + ['outbox_id' => $outboxId, 'message' => $message],
            'sent_at' => $sent ? date('Y-m-d H:i:s') : null,
        ]);

        return ['status' => $status, 'message' => $message, 'log_id' => $logId, 'outbox_id' => $outboxId];
    }

    private function address(string $email, string $name = ''): string
    {
        return $name !== '' ? $this->encodeHeader($name) . ' <' . $email . '>' : $email;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
