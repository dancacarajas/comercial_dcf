<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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
            'items' => $model->paginate($page, $perPage),
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
}
