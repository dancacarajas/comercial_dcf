<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\MailSetting;
use App\Services\MailerService;

final class EmailSettingsController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requirePermission('email_settings.view');

        $model = new MailSetting();
        $settings = $model->current();
        $hasPassword = !empty($settings['smtp_password_encrypted']);
        unset($settings['smtp_password_encrypted']);

        $this->view('email_settings/index', [
            'title' => 'Configuracao de E-mail',
            'settings' => $settings,
            'hasPassword' => $hasPassword,
            'providers' => $model->providers(),
            'encryptions' => $model->encryptions(),
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        AuthMiddleware::requirePermission('email_settings.edit');
        csrf_verify();

        $model = new MailSetting();
        $current = $model->current();
        $data = $this->collectSettingsInput();
        $errors = $model->validate($data, !empty($current['smtp_password_encrypted']));
        if ($errors !== []) {
            http_response_code(422);
            $safe = $data;
            $this->view('email_settings/index', [
                'title' => 'Configuracao de E-mail',
                'settings' => $safe,
                'hasPassword' => !empty($current['smtp_password_encrypted']),
                'providers' => $model->providers(),
                'encryptions' => $model->encryptions(),
                'errors' => $errors,
            ]);
            return;
        }

        $model->saveSettings($data);
        (new ActivityLog())->record('email_settings_updated', $_SESSION['user_id'] ?? null, 'mail_settings', (int) $current['id']);
        flash('success', 'Configuracao de e-mail atualizada.');
        $this->redirect('/settings/email');
    }

    public function test(): void
    {
        AuthMiddleware::requirePermission('email_settings.test');
        csrf_verify();

        $email = clean((string) input('test_email', ''));
        $name = clean((string) input('test_name', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Informe um e-mail de teste valido.');
            $this->redirect('/settings/email');
            return;
        }

        $result = (new MailerService())->sendTest($email, $name);
        (new MailSetting())->markTest($result['status'], $result['message']);
        (new ActivityLog())->record('email_settings_test_sent', $_SESSION['user_id'] ?? null, 'email_log', (int) $result['log_id']);

        $kind = $result['status'] === 'sent' ? 'success' : (in_array($result['status'], ['simulated', 'skipped'], true) ? 'warning' : 'error');
        flash($kind, 'Teste de e-mail: ' . $result['message']);
        $this->redirect('/settings/email');
    }

    public function templates(): void
    {
        AuthMiddleware::requirePermission('email_templates.view');

        $model = new EmailTemplate();
        $page = max(1, (int) input('page', 1));
        $perPage = 30;
        $total = $model->count();
        $pages = (int) max(1, ceil($total / $perPage));
        $page = min($page, $pages);

        $this->view('email_settings/templates', [
            'title' => 'Templates de E-mail',
            'items' => $this->prepareTemplatePreviews($model->paginate($page, $perPage)),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function logs(): void
    {
        AuthMiddleware::requirePermission('email_logs.view');

        $model = new EmailLog();
        $filters = [
            'q' => trim((string) input('q', '')),
            'status' => trim((string) input('status', '')),
            'event_key' => trim((string) input('event_key', '')),
        ];
        $page = max(1, (int) input('page', 1));
        $perPage = 30;
        $total = $model->count($filters);
        $pages = (int) max(1, ceil($total / $perPage));
        $page = min($page, $pages);

        $this->view('email_settings/logs', [
            'title' => 'Logs de E-mail',
            'items' => $model->paginate($filters, $page, $perPage),
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function resend(array $params): void
    {
        if (!can('email_logs.resend') && !can('email_settings.test')) {
            http_response_code(403);
            flash('error', 'Sem permissao para reenviar e-mail.');
            $this->redirect('/settings/email/logs');
            return;
        }
        csrf_verify();

        $outboxId = (int) ($params['id'] ?? 0);
        $row = $outboxId > 0 ? Database::run('SELECT * FROM `email_outbox` WHERE `id` = :id LIMIT 1', ['id' => $outboxId])->fetch() : false;
        if ($row === false) {
            flash('error', 'Registro de e-mail nao encontrado para reenvio.');
            $this->redirect('/settings/email/logs');
            return;
        }

        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['resent_from_outbox_id'] = $outboxId;
        $payload['resent_by'] = $_SESSION['user_id'] ?? null;
        $payload['resent_at'] = date('Y-m-d H:i:s');

        $result = (new MailerService())->send([
            'event_key' => (string) ($row['event_key'] ?? 'manual_resend'),
            'entity_type' => (string) ($row['entity_type'] ?? ''),
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'recipient_type' => (string) ($row['recipient_type'] ?? ''),
            'to_email' => (string) ($row['recipient_email'] ?? ''),
            'to_name' => (string) ($row['recipient_name'] ?? ''),
            'subject' => (string) ($row['subject'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'body_html' => (string) ($row['body_html'] ?? ''),
            'payload' => $payload,
        ]);

        (new ActivityLog())->record('email_outbox_resent', $_SESSION['user_id'] ?? null, 'email_outbox', $outboxId);

        $kind = $result['status'] === 'sent' ? 'success' : (in_array($result['status'], ['simulated', 'skipped'], true) ? 'warning' : 'error');
        flash($kind, 'Reenvio de e-mail: ' . $result['message']);
        $this->redirect('/settings/email/logs');
    }

    /** @return array<string, mixed> */
    private function collectSettingsInput(): array
    {
        $provider = (string) input('provider', 'gmail');
        $isGmail = $provider === 'gmail';
        return [
            'provider' => $provider,
            'smtp_host' => clean((string) input('smtp_host', $isGmail ? 'smtp.gmail.com' : '')),
            'smtp_port' => (int) input('smtp_port', 587),
            'smtp_encryption' => clean((string) input('smtp_encryption', 'tls')),
            'smtp_username' => clean((string) input('smtp_username', '')),
            'smtp_password' => (string) input('smtp_password', ''),
            'from_name' => clean((string) input('from_name', 'Danca Carajas Captacao')),
            'from_email' => clean((string) input('from_email', '')),
            'reply_to_name' => clean((string) input('reply_to_name', 'Equipe Danca Carajas')),
            'reply_to_email' => clean((string) input('reply_to_email', '')),
            'enabled' => (int) input('enabled', 0) === 1,
            'dry_run' => (int) input('dry_run', 0) === 1,
            'hourly_limit' => (int) input('hourly_limit', 20),
            'daily_limit' => (int) input('daily_limit', 100),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareTemplatePreviews(array $items): array
    {
        $vars = $this->previewVariables();

        foreach ($items as &$item) {
            $item['preview_subject'] = $this->renderTemplateText((string) ($item['subject'] ?? ''), $vars);
            $item['preview_text'] = $this->renderTemplateText((string) ($item['body_text'] ?? ''), $vars);
            $item['preview_html'] = $this->renderTemplateText((string) ($item['body_html'] ?? ''), $vars);
        }
        unset($item);

        return $items;
    }

    /** @return array<string, string> */
    private function previewVariables(): array
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $baseUrl = rtrim((string) ($config['url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://comercial.dancacarajas.com.br';
        }

        $branding = (array) ($config['organization']['branding'] ?? []);
        $festivalLogo = trim((string) ($branding['festival_logo'] ?? 'assets/img/branding/danca-carajas-logo.png'), '/');
        $producerLogo = trim((string) ($branding['producer_logo'] ?? 'assets/img/branding/ja-producoes-logo.png'), '/');

        return [
            'name' => 'JARBAS TESTE 2026 EMAIL',
            'email' => 'jarbasrh@gmail.com',
            'application_number' => 'CAP-2026-748A6D',
            'city_state' => 'Parauapebas/PA',
            'public_url' => $baseUrl . '/captadores/credenciamento/preview-token',
            'login_url' => $baseUrl . '/login',
            'portal_url' => $baseUrl . '/portal',
            'documents_list' => "- Documento de identificacao\n- Comprovante de endereco\n- Dados bancarios\n- Termo de credenciamento",
            'review_notes' => 'Ajuste o comprovante de endereco e envie novamente pelo link seguro.',
            'rejection_reason' => 'Criterios internos de credenciamento nao atendidos neste momento.',
            'signature_url' => $baseUrl . '/assinaturas/preview-token',
            'festival_logo_url' => $baseUrl . '/' . $festivalLogo,
            'producer_logo_url' => $baseUrl . '/' . $producerLogo,
            'support_email' => trim((string) (($config['organization']['email'] ?? '') ?: 'dancacarajas@gmail.com')),
        ];
    }

    /** @param array<string, string> $vars */
    private function renderTemplateText(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }
}
