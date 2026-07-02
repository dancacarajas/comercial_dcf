<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\EmailTemplate;
use App\Models\MailSetting;
use Throwable;

/**
 * Dispara e-mails transacionais por evento sem interromper o fluxo principal.
 */
final class EmailEventService
{
    /**
     * @param array<string, mixed> $application
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function sendToCollector(string $eventKey, array $application, array $extra = []): array
    {
        return $this->sendApplicationEvent($eventKey, $application, 'captador', $extra);
    }

    /**
     * @param array<string, mixed> $application
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function sendToTeam(string $eventKey, array $application, array $extra = []): array
    {
        return $this->sendApplicationEvent($eventKey, $application, 'equipe', $extra);
    }

    /**
     * @param array<string, mixed> $application
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function sendApplicationEvent(string $eventKey, array $application, string $recipientType, array $extra): array
    {
        try {
            $applicationId = (int) ($application['id'] ?? 0);
            $recipient = $this->recipientFor($recipientType, $application);
            if ($applicationId <= 0 || $recipient['email'] === '') {
                return ['status' => 'skipped', 'message' => 'Evento sem candidatura ou destinatario valido.'];
            }

            if ($this->alreadyRegistered($eventKey, 'collector_application', $applicationId, $recipientType, $recipient['email'])) {
                return ['status' => 'skipped', 'message' => 'Evento de e-mail ja registrado para esta candidatura.'];
            }

            $template = (new EmailTemplate())->findByEvent($eventKey);
            if ($template === null || (int) ($template['enabled'] ?? 0) !== 1) {
                return ['status' => 'skipped', 'message' => 'Template de e-mail desativado ou ausente: ' . $eventKey];
            }

            $vars = $this->variables($application, $extra);
            $result = (new MailerService())->send([
                'event_key' => $eventKey,
                'entity_type' => 'collector_application',
                'entity_id' => $applicationId,
                'recipient_type' => $recipientType,
                'to_email' => $recipient['email'],
                'to_name' => $recipient['name'],
                'subject' => $this->render((string) $template['subject'], $vars),
                'body_text' => $this->render((string) ($template['body_text'] ?? ''), $vars),
                'body_html' => $this->render((string) ($template['body_html'] ?? ''), $vars),
                'payload' => [
                    'application_id' => $applicationId,
                    'application_number' => (string) ($application['application_number'] ?? ''),
                    'recipient_type' => $recipientType,
                    'source_event' => $eventKey,
                ] + $extra,
            ]);

            (new ActivityLog())->record('email_event_dispatched_' . $eventKey, null, 'collector_application', $applicationId);

            return $result;
        } catch (Throwable $e) {
            error_log('[EmailEventService] ' . $eventKey . ': ' . $e->getMessage());
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /** @return array{email:string,name:string} */
    private function recipientFor(string $recipientType, array $application): array
    {
        if ($recipientType === 'equipe') {
            $settings = (new MailSetting())->current();
            $email = trim((string) (($settings['reply_to_email'] ?? '') ?: (($settings['from_email'] ?? '') ?: ($settings['smtp_username'] ?? ''))));
            $name = trim((string) (($settings['reply_to_name'] ?? '') ?: (($settings['from_name'] ?? '') ?: 'Equipe Danca Carajas')));

            return ['email' => $email, 'name' => $name];
        }

        return [
            'email' => strtolower(trim((string) ($application['email'] ?? ''))),
            'name' => trim((string) ($application['name'] ?? '')),
        ];
    }

    private function alreadyRegistered(string $eventKey, string $entityType, int $entityId, string $recipientType, string $email): bool
    {
        try {
            $row = Database::run(
                'SELECT `id` FROM `email_outbox`
                  WHERE `event_key` = :event_key
                    AND `entity_type` = :entity_type
                    AND `entity_id` = :entity_id
                    AND `recipient_type` = :recipient_type
                    AND `recipient_email` = :email
                    AND `status` IN (\'sent\', \'simulated\')
                  LIMIT 1',
                [
                    'event_key' => $eventKey,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'recipient_type' => $recipientType,
                    'email' => $email,
                ]
            )->fetch();

            return $row !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $extra */
    private function variables(array $application, array $extra): array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $baseUrl = rtrim((string) ($config['url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://comercial.dancacarajas.com.br';
        }
        $branding = (array) ($config['organization']['branding'] ?? []);
        $festivalLogo = trim((string) ($branding['festival_logo'] ?? 'assets/img/branding/danca-carajas-logo.png'), '/');
        $producerLogo = trim((string) ($branding['producer_logo'] ?? 'assets/img/branding/ja-producoes-logo.png'), '/');
        $publicToken = trim((string) ($application['public_token'] ?? ''));
        $publicUrl = (string) ($extra['public_url'] ?? '');
        if ($publicUrl === '' && $publicToken !== '') {
            $publicUrl = $baseUrl . '/captadores/credenciamento/' . rawurlencode($publicToken);
        }

        return [
            'name' => trim((string) ($application['name'] ?? '')),
            'email' => trim((string) ($application['email'] ?? '')),
            'application_number' => trim((string) ($application['application_number'] ?? '')),
            'city_state' => trim((string) ($application['city_state'] ?? '')),
            'public_url' => $publicUrl,
            'login_url' => $baseUrl . '/login',
            'portal_url' => $baseUrl . '/portal',
            'documents_list' => (string) ($extra['documents_list'] ?? ''),
            'review_notes' => (string) ($extra['review_notes'] ?? ($application['review_notes'] ?? '')),
            'rejection_reason' => (string) ($extra['rejection_reason'] ?? ($application['rejection_reason'] ?? '')),
            'signature_url' => (string) ($extra['signature_url'] ?? ''),
            'festival_logo_url' => $baseUrl . '/' . $festivalLogo,
            'producer_logo_url' => $baseUrl . '/' . $producerLogo,
            'support_email' => trim((string) (($config['organization']['email'] ?? '') ?: 'dancacarajas@gmail.com')),
        ];
    }

    /** @param array<string, string> $vars */
    private function render(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }
}
